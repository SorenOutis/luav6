<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\CommunityProfileFeatured;
use App\Models\CommunityStory;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PublicFileUrl;
use App\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CommunityController extends Controller
{
    /**
     * Display the community lounge feed.
     */
    public function index(Request $request): Response
    {
        $viewer = $request->user();
        $workspace = $viewer?->currentWorkspace;

        if (! $viewer?->hasLoungeAccess()) {
            return Inertia::render('CommunityLocked', [
                'workspaceName' => $workspace?->name,
                'studentName' => $viewer?->name,
                'studentEmail' => $viewer?->email,
                'hasPendingInvite' => (bool) $viewer?->communityInvite()->exists(),
            ]);
        }

        // Record viewer presence with 120s RAM TTL and throttled DB write
        $this->recordPresence($viewer);

        // Determine viewer's active section
        $section = $this->resolveViewerSection($viewer);

        // Fetch section presence list (cached in RAM for 15s to prevent DB overload)
        $presenceData = $this->getSectionPresenceData($section, $viewer);

        // Filter / tab
        $tab = $request->query('tab', 'all');

        // Query posts
        $posts = $this->getPosts($viewer, $section, $tab);

        // Trending topics
        $trendingTopics = $this->getTrendingTopics($workspace?->id);

        // Active 24h stories tray
        $stories = $this->getActiveStoriesTray($viewer, $workspace, $section);

        $isFirstTimeJoin = (bool) $request->session()->get('first_time_lounge_join', false);

        return Inertia::render('Community', [
            'workspace' => $workspace,
            'sectionName' => $section?->name ?? 'Your Section',
            'onlineClassmates' => $presenceData['classmates'],
            'onlineCount' => $presenceData['onlineCount'],
            'totalEnrolledCount' => $presenceData['totalEnrolled'],
            'posts' => $posts,
            'trendingTopics' => $trendingTopics,
            'currentTab' => $tab,
            'stories' => $stories,
            'isFirstTimeJoin' => $isFirstTimeJoin,
        ]);
    }

    /**
     * Lightweight polling endpoint for presence and live counts.
     */
    public function presence(Request $request): JsonResponse
    {
        $viewer = $request->user();
        if (! $viewer?->hasLoungeAccess()) {
            return response()->json(['error' => 'Lounge locked'], 403);
        }

        $this->recordPresence($viewer);

        $section = $this->resolveViewerSection($viewer);
        $presenceData = $this->getSectionPresenceData($section, $viewer);

        return response()->json([
            'sectionName' => $section?->name ?? 'Your Section',
            'onlineClassmates' => $presenceData['classmates'],
            'onlineCount' => $presenceData['onlineCount'],
            'totalEnrolledCount' => $presenceData['totalEnrolled'],
        ]);
    }

    /**
     * Instagram-style profile preview modal data.
     */
    public function userPreview(Request $request, User $user): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $sharesSection = $user->sections()
            ->whereIn('sections.id', $viewer->sections()->pluck('sections.id'))
            ->exists();

        $isSelf = $viewer->is($user);
        $isMasked = ! $sharesSection && ! $isSelf;

        if ($isMasked) {
            return response()->json([
                'is_masked' => true,
                'is_self' => false,
                'public_id' => null,
                'name' => 'Student #'.substr(md5((string) $user->id), 0, 4),
                'handle' => 'student.'.substr(md5((string) $user->id), 0, 4),
                'avatar' => null,
                'bio' => 'Identity protected for cross-section student privacy.',
                'section_name' => 'Other Section',
                'can_follow' => false,
                'is_following' => false,
                'followers_count' => 0,
                'following_count' => 0,
                'posts_count' => 0,
                'likes_received' => 0,
                'recent_posts' => [],
            ]);
        }

        $isFollowing = $viewer->following()->where('users.id', $user->id)->exists();
        $canFollow = ! $viewer->is_admin && ! $user->is_admin && ! $isSelf && $sharesSection;

        $recentPosts = CommunityPost::query()
            ->where('user_id', $user->id)
            ->where('is_anonymous', false)
            ->latest()
            ->limit(6)
            ->get(['id', 'content', 'attachment_url', 'attachment_type', 'created_at', 'likes_count', 'comments_count'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'content' => Str::limit($p->content, 90),
                'attachment_url' => $p->attachment_url ? PublicFileUrl::resolve($p->attachment_url) : null,
                'attachment_type' => $p->attachment_type,
                'likes_count' => (int) $p->likes_count,
                'comments_count' => (int) $p->comments_count,
                'created_at' => $p->created_at->diffForHumans(short: true),
            ]);

        $userSection = $user->sections()->first();

        $hasActiveStories = CommunityStory::active()
            ->where('user_id', $user->id)
            ->exists();

        return response()->json([
            'is_masked' => false,
            'is_self' => $isSelf,
            'public_id' => (string) $user->public_id,
            'name' => $user->name,
            'handle' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $user->name)),
            'avatar' => $user->avatar ? PublicFileUrl::resolve($user->avatar) : null,
            'has_active_stories' => $hasActiveStories,
            'bio' => $user->bio ?: 'Student · Learning in Lounge',
            'level' => (int) ($user->level ?? 1),
            'section_name' => $userSection?->name ?? 'Your Section',
            'can_follow' => $canFollow,
            'is_following' => $isFollowing,
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'posts_count' => $user->communityPosts()->where('is_anonymous', false)->count(),
            'likes_received' => (int) $user->communityPosts()->where('is_anonymous', false)->sum('likes_count'),
            'recent_posts' => $recentPosts,
        ]);
    }

    /**
     * Dedicated Community Lounge Profile page.
     * Distinct from the academic learning platform profile.
     */
    public function userProfile(Request $request, User $user): Response
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);

        if (! $viewer->hasLoungeAccess()) {
            return Inertia::render('CommunityLocked', [
                'workspaceName' => $viewer->currentWorkspace?->name,
                'studentName' => $viewer->name,
                'studentEmail' => $viewer->email,
                'hasPendingInvite' => (bool) $viewer->communityInvite()->exists(),
            ]);
        }

        $this->recordPresence($viewer);

        $sharesSection = $user->sections()
            ->whereIn('sections.id', $viewer->sections()->pluck('sections.id'))
            ->exists();

        $isSelf = $viewer->is($user);
        $isMasked = ! $sharesSection && ! $isSelf;

        if ($isMasked) {
            return Inertia::render('CommunityProfile', [
                'profile' => [
                    'is_masked' => true,
                    'is_self' => false,
                    'public_id' => null,
                    'name' => 'Student #'.substr(md5((string) $user->id), 0, 4),
                    'handle' => 'student.'.substr(md5((string) $user->id), 0, 4),
                    'avatar' => null,
                    'bio' => 'Identity and lounge activity shielded for cross-section student privacy.',
                    'level' => 1,
                    'section_name' => 'Other Section',
                    'presence_status' => 'offline',
                    'presence_activity' => 'Privacy Shielded',
                    'can_follow' => false,
                    'is_following' => false,
                    'followers_count' => 0,
                    'following_count' => 0,
                    'posts_count' => 0,
                    'likes_received' => 0,
                    'posts' => [],
                    'saved_posts' => [],
                    'featured_media' => [],
                ],
            ]);
        }

        // Presence calculation
        $presence = Cache::get("user:presence:{$user->id}");
        $now = now()->timestamp;
        $lastSeen = $presence['seen'] ?? ($user->last_seen_at ? Carbon::parse($user->last_seen_at)->timestamp : 0);
        $diff = $now - $lastSeen;

        if ($isSelf || $diff <= 120) {
            $presenceStatus = 'online';
            $presenceActivity = $presence['activity'] ?? 'Active in Lounge';
        } elseif ($diff <= 600) {
            $presenceStatus = 'idle';
            $minutes = max(1, (int) round($diff / 60));
            $presenceActivity = "Idle ({$minutes}m)";
        } else {
            $presenceStatus = 'offline';
            $presenceActivity = $lastSeen > 0
                ? 'Last seen '.Carbon::createFromTimestamp($lastSeen)->diffForHumans(short: true)
                : 'Offline';
        }

        $isFollowing = $viewer->following()->where('users.id', $user->id)->exists();
        $canFollow = ! $viewer->is_admin && ! $user->is_admin && ! $isSelf && $sharesSection;
        $userSection = $user->sections()->first();

        // Lounge posts for this user
        $posts = CommunityPost::query()
            ->where('user_id', $user->id)
            ->when(! $isSelf, fn ($q) => $q->where('is_anonymous', false))
            ->with(['section:id,name'])
            ->latest()
            ->limit(50)
            ->get();

        $viewerLikedIds = DB::table('community_post_likes')
            ->where('user_id', $viewer->id)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('post_id')
            ->all();

        $viewerBookmarkedIds = DB::table('community_post_bookmarks')
            ->where('user_id', $viewer->id)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('post_id')
            ->all();

        $postsData = $posts->map(fn (CommunityPost $p) => [
            'id' => $p->id,
            'content' => $p->content,
            'attachment_url' => $p->attachment_url ? PublicFileUrl::resolve($p->attachment_url) : null,
            'attachment_type' => $p->attachment_type,
            'attachment_name' => $p->attachment_name,
            'attachment_size' => $p->attachment_size,
            'likes_count' => (int) $p->likes_count,
            'comments_count' => (int) $p->comments_count,
            'created_at' => $p->created_at->diffForHumans(short: true),
            'is_liked' => in_array($p->id, $viewerLikedIds, true),
            'is_bookmarked' => in_array($p->id, $viewerBookmarkedIds, true),
            'is_anonymous' => (bool) $p->is_anonymous,
            'section_name' => $p->section?->name ?? ($userSection?->name ?? 'Section'),
        ])->all();

        // Saved lounge posts (only visible when viewing self)
        $savedPosts = [];

        // Pinned featured media (Meta / Facebook style highlights, up to 6 items)
        $featuredMedia = CommunityProfileFeatured::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order', 'asc')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (CommunityProfileFeatured $f) => [
                'id' => $f->id,
                'media_url' => PublicFileUrl::resolve($f->media_url),
                'media_type' => $f->media_type,
                'thumbnail_url' => $f->thumbnail_url ? PublicFileUrl::resolve($f->thumbnail_url) : null,
                'title' => $f->title,
                'sort_order' => (int) $f->sort_order,
                'created_at' => $f->created_at->diffForHumans(short: true),
            ])->all();
        if ($isSelf) {
            $saved = CommunityPost::query()
                ->whereHas('bookmarks', fn ($q) => $q->where('user_id', $viewer->id))
                ->with(['user:id,public_id,name,avatar', 'section:id,name'])
                ->latest()
                ->limit(50)
                ->get();

            $savedPosts = $saved->map(fn (CommunityPost $p) => [
                'id' => $p->id,
                'content' => $p->content,
                'attachment_url' => $p->attachment_url ? PublicFileUrl::resolve($p->attachment_url) : null,
                'attachment_type' => $p->attachment_type,
                'attachment_name' => $p->attachment_name,
                'attachment_size' => $p->attachment_size,
                'likes_count' => (int) $p->likes_count,
                'comments_count' => (int) $p->comments_count,
                'created_at' => $p->created_at->diffForHumans(short: true),
                'author_name' => $p->user?->name ?? 'Student',
                'author_handle' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $p->user?->name ?? 'student')),
                'author_avatar' => $p->user?->avatar ? PublicFileUrl::resolve($p->user->avatar) : null,
                'section_name' => $p->section?->name ?? 'Section',
            ])->all();
        }

        return Inertia::render('CommunityProfile', [
            'profile' => [
                'is_masked' => false,
                'is_self' => $isSelf,
                'public_id' => (string) $user->public_id,
                'name' => $user->name,
                'handle' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $user->name)),
                'avatar' => $user->avatar ? PublicFileUrl::resolve($user->avatar) : null,
                'bio' => $user->bio ?: 'Student · Learning in Lounge',
                'level' => (int) ($user->level ?? 1),
                'section_name' => $userSection?->name ?? 'Your Section',
                'presence_status' => $presenceStatus,
                'presence_activity' => $presenceActivity,
                'can_follow' => $canFollow,
                'is_following' => $isFollowing,
                'followers_count' => $user->followers()->count(),
                'following_count' => $user->following()->count(),
                'posts_count' => $user->communityPosts()->when(! $isSelf, fn ($q) => $q->where('is_anonymous', false))->count(),
                'likes_received' => (int) $user->communityPosts()->when(! $isSelf, fn ($q) => $q->where('is_anonymous', false))->sum('likes_count'),
                'posts' => $postsData,
                'saved_posts' => $savedPosts,
                'featured_media' => $featuredMedia,
            ],
        ]);
    }

    /**
     * Record user presence in Cache (120s TTL). Throttles DB write to once every 3 minutes.
     */
    private function recordPresence(?User $user): void
    {
        if (! $user) {
            return;
        }

        $now = now()->timestamp;
        Cache::put("user:presence:{$user->id}", [
            'seen' => $now,
            'activity' => 'Active in Lounge',
        ], 120);

        // Write to DB at most once every 3 minutes per user to protect database under heavy polling
        $throttleKey = "user:last_seen_db:{$user->id}";
        if (Cache::add($throttleKey, 1, 180)) {
            DB::table('users')->where('id', $user->id)->update([
                'last_seen_at' => now(),
            ]);
        }
    }

    /**
     * Resolve the viewer's current section in this workspace.
     */
    private function resolveViewerSection(?User $user): ?Section
    {
        if (! $user) {
            return null;
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        return $user->sections()
            ->when($workspaceId, fn ($q) => $q->where('sections.workspace_id', $workspaceId))
            ->first();
    }

    /**
     * Get section or community presence data with a 15-second RAM cache.
     * Over 95% of background polling calls hit memory, keeping DB CPU near 0%.
     *
     * @return array{classmates: array, onlineCount: int, totalEnrolled: int}
     */
    private function getSectionPresenceData(?Section $section, ?User $viewer): array
    {
        $workspace = $viewer?->currentWorkspace;
        $workspaceId = $workspace?->id ?? app(WorkspaceContext::class)->id();

        if (! $workspaceId && ! $section) {
            return [
                'classmates' => [],
                'onlineCount' => 0,
                'totalEnrolled' => 0,
            ];
        }

        $cacheKey = $workspaceId
            ? "workspace:{$workspaceId}:presence_data"
            : "section:{$section->id}:presence_data";

        $data = Cache::remember($cacheKey, 15, function () use ($section, $workspaceId): array {
            $query = User::query()->where('is_admin', false);

            if ($workspaceId) {
                $query->where(function ($sub) use ($workspaceId) {
                    $sub->whereHas('workspaces', fn ($q) => $q->whereKey($workspaceId))
                        ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                        ->orWhere('users.current_workspace_id', $workspaceId);
                });
            } else {
                $query->whereHas('sections', fn ($q) => $q->whereKey($section->id));
            }

            $members = $query
                ->with(['sections' => fn ($q) => $q->select(['sections.id', 'sections.name'])])
                ->select(['users.id', 'users.public_id', 'users.name', 'users.avatar', 'users.last_seen_at'])
                ->get();

            $now = now()->timestamp;
            $classmates = [];
            $onlineCount = 0;

            foreach ($members as $member) {
                // Check cache first for sub-minute presence
                $presence = Cache::get("user:presence:{$member->id}");
                $lastSeen = $presence['seen'] ?? ($member->last_seen_at ? Carbon::parse($member->last_seen_at)->timestamp : 0);
                $diff = $now - $lastSeen;

                if ($diff <= 120) {
                    $status = 'online';
                    $activity = $presence['activity'] ?? 'Active in Lounge';
                    $onlineCount++;
                } elseif ($diff <= 600) {
                    $status = 'idle';
                    $minutes = max(1, (int) round($diff / 60));
                    $activity = "Idle ({$minutes}m)";
                } else {
                    $status = 'offline';
                    $activity = $lastSeen > 0
                        ? 'Last seen '.Carbon::createFromTimestamp($lastSeen)->diffForHumans(short: true)
                        : 'Offline';
                }

                $classmates[] = [
                    'id' => (string) $member->id,
                    'public_id' => (string) $member->public_id,
                    'name' => $member->name,
                    'avatar' => $member->avatar ? PublicFileUrl::resolve($member->avatar) : null,
                    'section_name' => $member->sections->first()?->name,
                    'status' => $status,
                    'activity' => $activity,
                ];
            }

            // Sort: online first, then idle, then offline; within same status: alphabetical by name
            usort($classmates, function ($a, $b) {
                $p = ['online' => 0, 'idle' => 1, 'offline' => 2];
                $statusDiff = ($p[$a['status']] ?? 3) <=> ($p[$b['status']] ?? 3);
                if ($statusDiff !== 0) {
                    return $statusDiff;
                }

                return strcasecmp($a['name'], $b['name']);
            });

            return [
                'classmates' => $classmates,
                'onlineCount' => $onlineCount,
                'totalEnrolled' => count($classmates),
            ];
        });

        // Mark viewer record as isCurrentUser
        if ($viewer) {
            foreach ($data['classmates'] as &$c) {
                if ((int) $c['id'] === (int) $viewer->id) {
                    $c['isCurrentUser'] = true;
                    $c['status'] = 'online';
                    $c['activity'] = 'Active in Lounge';
                } else {
                    $c['isCurrentUser'] = false;
                }
            }
            unset($c);
        }

        return $data;
    }

    /**
     * Query posts with pagination, user masking, and interaction state.
     */
    private function getPosts(?User $viewer, ?Section $section, string $tab): array
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        $query = CommunityPost::query()
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->with([
                'user:id,public_id,name,avatar,level',
                'section:id,name',
                'comments' => fn ($q) => $q->whereNull('parent_id')
                    ->latest()
                    ->limit(10)
                    ->with([
                        'user' => fn ($uq) => $uq->select('id', 'public_id', 'name', 'avatar', 'level')->with('sections:id,name'),
                        'replies' => fn ($rq) => $rq->oldest()->with([
                            'user' => fn ($uq) => $uq->select('id', 'public_id', 'name', 'avatar', 'level')->with('sections:id,name'),
                        ]),
                    ]),
            ])
            ->latest();

        if ($tab === 'section' && $section) {
            $query->where('section_id', $section->id);
        } elseif ($tab === 'saved' && $viewer) {
            $query->whereHas('bookmarks', fn ($q) => $q->where('user_id', $viewer->id));
        }

        $posts = $query->limit(30)->get();

        $viewerSectionId = $section?->id;
        $viewerLikedIds = $viewer
            ? DB::table('community_post_likes')
                ->where('user_id', $viewer->id)
                ->whereIn('post_id', $posts->pluck('id'))
                ->pluck('post_id')
                ->all()
            : [];

        $viewerBookmarkedIds = $viewer
            ? DB::table('community_post_bookmarks')
                ->where('user_id', $viewer->id)
                ->whereIn('post_id', $posts->pluck('id'))
                ->pluck('post_id')
                ->all()
            : [];

        $viewerFollowingIds = $viewer
            ? $viewer->following()->whereIn('users.id', $posts->pluck('user_id'))->pluck('users.id')->all()
            : [];

        $allCommentIds = $posts->flatMap(function (CommunityPost $p) {
            return $p->comments->flatMap(function ($c) {
                return collect([$c->id])->concat($c->replies->pluck('id'));
            });
        })->all();

        $viewerCommentLikedIds = $viewer && ! empty($allCommentIds)
            ? DB::table('community_comment_likes')
                ->where('user_id', $viewer->id)
                ->whereIn('comment_id', $allCommentIds)
                ->pluck('comment_id')
                ->all()
            : [];

        return $posts->map(function (CommunityPost $post) use ($viewer, $section, $viewerSectionId, $viewerLikedIds, $viewerBookmarkedIds, $viewerFollowingIds, $viewerCommentLikedIds): array {
            $author = $post->user;
            $isSameSection = $viewerSectionId && $post->section_id === $viewerSectionId;
            $isOwnPost = $viewer && $post->user_id === $viewer->id;
            $isMasked = ($post->is_anonymous || ! $isSameSection) && ! $isOwnPost;

            $handle = $isMasked
                ? 'student.'.substr(md5((string) $post->user_id), 0, 4)
                : strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $author?->name ?? 'student'));

            $displayName = $isMasked
                ? 'Student #'.substr(md5((string) $post->user_id), 0, 4)
                : ($author?->name ?? 'Student');

            $avatarUrl = $isMasked
                ? null
                : ($author?->avatar ? PublicFileUrl::resolve($author->avatar) : null);

            return [
                'id' => $post->id,
                'content' => $post->content,
                'created_at' => $post->created_at->diffForHumans(short: true),
                'attachment_url' => $post->attachment_url ? PublicFileUrl::resolve($post->attachment_url) : null,
                'attachment_type' => $post->attachment_type,
                'attachment_name' => $post->attachment_name,
                'attachment_size' => $post->attachment_size,
                'likes_count' => (int) $post->likes_count,
                'comments_count' => (int) $post->comments_count,
                'is_liked' => in_array($post->id, $viewerLikedIds, true),
                'is_bookmarked' => in_array($post->id, $viewerBookmarkedIds, true),
                'author' => [
                    'id' => (string) ($author?->id ?? ''),
                    'public_id' => $isMasked ? null : (string) ($author?->public_id ?? ''),
                    'name' => $displayName,
                    'handle' => $handle,
                    'avatar' => $avatarUrl,
                    'is_same_section' => $isSameSection,
                    'is_masked' => $isMasked,
                    'is_following' => in_array($post->user_id, $viewerFollowingIds, true),
                    'section_name' => $post->section?->name ?? 'Workspace',
                ],
                'comments' => $post->comments->map(function ($c) use ($viewer, $viewerSectionId, $section, $viewerCommentLikedIds) {
                    $cAuthor = $c->user;
                    $isSame = $viewerSectionId && $viewer && $cAuthor && $cAuthor->sections->contains('id', $viewerSectionId);
                    $isOwn = $viewer && $c->user_id === $viewer->id;
                    $masked = ! $isSame && ! $isOwn;

                    return [
                        'id' => $c->id,
                        'parent_id' => $c->parent_id,
                        'content' => $c->content,
                        'created_at' => $c->created_at->diffForHumans(short: true),
                        'author_id' => (string) ($cAuthor?->id ?? ''),
                        'author_public_id' => $masked ? null : (string) ($cAuthor?->public_id ?? ''),
                        'author_name' => $masked ? 'Student #'.substr(md5((string) $c->user_id), 0, 4) : ($cAuthor?->name ?? 'Student'),
                        'author_handle' => $masked ? 'student.'.substr(md5((string) $c->user_id), 0, 4) : strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $cAuthor?->name ?? 'student')),
                        'author_avatar' => $masked ? null : ($cAuthor?->avatar ? PublicFileUrl::resolve($cAuthor->avatar) : null),
                        'is_masked' => $masked,
                        'section_name' => $cAuthor ? ($isSame ? ($section?->name ?? 'Your Section') : 'Other Section') : 'Workspace',
                        'likes_count' => (int) $c->likes_count,
                        'is_liked' => in_array($c->id, $viewerCommentLikedIds, true),
                        'replies' => $c->relationLoaded('replies')
                            ? $c->replies->map(function ($r) use ($viewer, $viewerSectionId, $section, $viewerCommentLikedIds) {
                                $rAuthor = $r->user;
                                $rIsSame = $viewerSectionId && $viewer && $rAuthor && $rAuthor->sections->contains('id', $viewerSectionId);
                                $rIsOwn = $viewer && $r->user_id === $viewer->id;
                                $rMasked = ! $rIsSame && ! $rIsOwn;

                                return [
                                    'id' => $r->id,
                                    'parent_id' => $r->parent_id,
                                    'content' => $r->content,
                                    'created_at' => $r->created_at->diffForHumans(short: true),
                                    'author_id' => (string) ($rAuthor?->id ?? ''),
                                    'author_public_id' => $rMasked ? null : (string) ($rAuthor?->public_id ?? ''),
                                    'author_name' => $rMasked ? 'Student #'.substr(md5((string) $r->user_id), 0, 4) : ($rAuthor?->name ?? 'Student'),
                                    'author_handle' => $rMasked ? 'student.'.substr(md5((string) $r->user_id), 0, 4) : strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $rAuthor?->name ?? 'student')),
                                    'author_avatar' => $rMasked ? null : ($rAuthor?->avatar ? PublicFileUrl::resolve($rAuthor->avatar) : null),
                                    'is_masked' => $rMasked,
                                    'section_name' => $rAuthor ? ($rIsSame ? ($section?->name ?? 'Your Section') : 'Other Section') : 'Workspace',
                                    'likes_count' => (int) $r->likes_count,
                                    'is_liked' => in_array($r->id, $viewerCommentLikedIds, true),
                                    'replies' => [],
                                ];
                            })->all()
                            : [],
                    ];
                })->all(),
            ];
        })->all();
    }

    /**
     * Get trending hashtags for this workspace (cached for 10 minutes).
     */
    private function getTrendingTopics(?int $workspaceId): array
    {
        $cacheKey = 'workspace:'.($workspaceId ?? 0).':trending_topics';

        return Cache::remember($cacheKey, 600, function () use ($workspaceId): array {
            $recentPosts = CommunityPost::query()
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->where('created_at', '>=', now()->subDays(7))
                ->pluck('content');

            $tagCounts = [];
            foreach ($recentPosts as $content) {
                if (preg_match_all('/#([a-zA-Z0-9_]+)/', $content, $matches)) {
                    foreach ($matches[1] as $tag) {
                        $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                    }
                }
            }

            if (! empty($tagCounts)) {
                arsort($tagCounts);
                $result = [];
                foreach (array_slice($tagCounts, 0, 5, true) as $tag => $count) {
                    $result[] = [
                        'tag' => strtolower($tag),
                        'name' => $tag,
                        'count' => $count,
                    ];
                }

                return $result;
            }

            return [];
        });
    }

    /**
     * Get active stories grouped by user for the stories tray.
     */
    private function getActiveStoriesTray(?User $viewer, ?Workspace $workspace, ?Section $section): array
    {
        if (! $viewer) {
            return [];
        }

        $viewerSectionIds = $viewer->sections()->pluck('sections.id')->all();

        $stories = CommunityStory::query()
            ->active()
            ->when($workspace?->id, fn ($q) => $q->where('workspace_id', $workspace->id))
            ->with(['user:id,public_id,name,avatar', 'reactions:id,story_id,user_id,reaction'])
            ->orderBy('created_at', 'asc')
            ->get();

        $grouped = $stories->groupBy('user_id');

        $viewerStoryGroup = null;
        $classmateGroups = [];

        foreach ($grouped as $userId => $userStories) {
            $user = $userStories->first()->user;
            if (! $user) {
                continue;
            }

            $isSelf = $viewer->id === (int) $userId;
            $sharesSection = $isSelf;
            if (! $isSelf && ! empty($viewerSectionIds)) {
                $sharesSection = $user->sections()->whereIn('sections.id', $viewerSectionIds)->exists();
            }

            $isMasked = ! $sharesSection && ! $isSelf;

            $items = $userStories->map(function (CommunityStory $s) use ($isSelf, $viewer) {
                $userReaction = $s->reactions->firstWhere('user_id', $viewer?->id)?->reaction;
                $reactionsCount = $s->reactions->count();

                return [
                    'id' => $s->id,
                    'media_url' => PublicFileUrl::resolve($s->media_url),
                    'media_type' => $s->media_type,
                    'duration_seconds' => (int) $s->duration_seconds,
                    'caption' => $s->caption,
                    'views_count' => (int) $s->views_count,
                    'reactions_count' => $reactionsCount,
                    'user_reaction' => $userReaction,
                    'is_reacted' => ! empty($userReaction),
                    'created_at' => $s->created_at->diffForHumans(short: true),
                    'is_self' => $isSelf,
                ];
            })->values()->all();

            $entry = [
                'user_id' => $user->id,
                'public_id' => $isMasked ? null : (string) $user->public_id,
                'name' => $isMasked ? 'Student #'.substr(md5((string) $user->id), 0, 4) : ($isSelf ? 'Your story' : $user->name),
                'handle' => $isMasked ? 'student.'.substr(md5((string) $user->id), 0, 4) : strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $user->name)),
                'avatar' => $isMasked ? null : ($user->avatar ? PublicFileUrl::resolve($user->avatar) : null),
                'is_self' => $isSelf,
                'is_masked' => $isMasked,
                'stories' => $items,
            ];

            if ($isSelf) {
                $viewerStoryGroup = $entry;
            } else {
                $classmateGroups[] = $entry;
            }
        }

        $tray = [];
        if ($viewerStoryGroup) {
            $tray[] = $viewerStoryGroup;
        }
        foreach ($classmateGroups as $cg) {
            $tray[] = $cg;
        }

        return $tray;
    }
}
