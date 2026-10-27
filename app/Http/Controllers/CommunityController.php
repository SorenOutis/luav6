<?php

namespace App\Http\Controllers;

use App\Models\CommunityComment;
use App\Models\CommunityPost;
use App\Models\CommunityProfileFeatured;
use App\Models\CommunityStory;
use App\Models\ProfileMusicTrack;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CommunitySettings;
use App\Support\PublicFileUrl;
use App\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $availableSections = $this->getAvailableSections($viewer, $workspace);
        $requestedSectionId = $request->query('section_id') ?? $request->session()->get('community_active_section_id');
        $section = $this->resolveViewerSection($viewer, $requestedSectionId);

        // Fetch section presence list (cached in RAM for 15s to prevent DB overload)
        $presenceData = $this->getSectionPresenceData($section, $viewer);

        // Filter / tab
        $tab = $request->query('tab', 'all');
        if (CommunitySettings::isRestrictedToSection() && $tab === 'section') {
            $tab = 'all';
        }

        // Query posts
        $posts = $this->getPosts($viewer, $section, $tab);

        // Trending topics (derived from visible feed posts so hidden/other-section posts never leak)
        $trendingTopics = $this->getTrendingTopics($posts);

        // Active 24h stories tray
        $stories = $this->getActiveStoriesTray($viewer, $workspace, $section);

        $isFirstTimeJoin = (bool) $request->session()->get('first_time_lounge_join', false);

        $userWorkspaceId = $workspace?->id ?? app(WorkspaceContext::class)->id();
        $musicTracks = CommunitySettings::musicEnabled()
            ? ProfileMusicTrack::query()
                ->where('is_active', true)
                ->availableForWorkspace($userWorkspaceId)
                ->orderBy('title')
                ->get()
                ->map(fn (ProfileMusicTrack $track) => [
                    'id' => $track->id,
                    'title' => $track->title,
                    'artist' => $track->artist,
                    'duration' => (float) $track->duration_seconds,
                    'audio_url' => route('profile-music.stream', $track->id),
                    'audioUrl' => route('profile-music.stream', $track->id),
                    'cover_image_url' => $track->cover_image_path
                        ? PublicFileUrl::resolve($track->cover_image_path)
                        : null,
                    'coverImageUrl' => $track->cover_image_path
                        ? PublicFileUrl::resolve($track->cover_image_path)
                        : null,
                ])
                ->values()
                ->all()
            : [];

        return Inertia::render('Community', [
            'workspace' => $workspace,
            'sectionName' => $section?->name ?? 'Your Section',
            'currentSectionId' => $section?->id,
            'availableSections' => $availableSections,
            'onlineClassmates' => $presenceData['classmates'],
            'onlineCount' => $presenceData['onlineCount'],
            'totalEnrolledCount' => $presenceData['totalEnrolled'],
            'posts' => $posts,
            'musicTracks' => $musicTracks,
            'trendingTopics' => $trendingTopics,
            'currentTab' => $tab,
            'stories' => $stories,
            'isFirstTimeJoin' => $isFirstTimeJoin,
            'isSectionOnly' => CommunitySettings::isRestrictedToSection(),
            'feedScope' => CommunitySettings::feedScope(),
            'allowsAnonymous' => CommunitySettings::allowsAnonymous(),
            'storiesEnabled' => CommunitySettings::storiesEnabled(),
            'musicEnabled' => CommunitySettings::musicEnabled(),
            'mediaUploadsEnabled' => CommunitySettings::mediaUploadsEnabled(),
            'fileAttachmentsEnabled' => CommunitySettings::fileAttachmentsEnabled(),
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

        $requestedSectionId = $request->query('section_id') ?? $request->session()->get('community_active_section_id');
        $section = $this->resolveViewerSection($viewer, $requestedSectionId);
        $presenceData = $this->getSectionPresenceData($section, $viewer);

        return response()->json([
            'sectionName' => $section?->name ?? 'Your Section',
            'sectionId' => $section?->id,
            'onlineClassmates' => $presenceData['classmates'],
            'onlineCount' => $presenceData['onlineCount'],
            'totalEnrolledCount' => $presenceData['totalEnrolled'],
        ]);
    }

    /**
     * Switch the active section in session without query string in URL.
     */
    public function selectSection(Request $request): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $validated = $request->validate([
            'section_id' => ['required', 'integer'],
        ]);

        $request->session()->put('community_active_section_id', (int) $validated['section_id']);

        return back();
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
            ->with(['section:id,name', 'media', 'musicTrack', ...$this->commentRelations($viewer)])
            ->latest()
            ->limit(50)
            ->get();

        $viewerLikedIds = DB::table('community_post_likes')
            ->where('user_id', $viewer->id)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('post_id')
            ->all();

        $allProfileMediaIds = $posts->pluck('media')->flatten()->pluck('id')->filter()->all();
        $viewerProfileMediaLikedIds = ! empty($allProfileMediaIds)
            ? DB::table('community_post_media_likes')
                ->where('user_id', $viewer->id)
                ->whereIn('post_media_id', $allProfileMediaIds)
                ->pluck('post_media_id')
                ->all()
            : [];

        $viewerBookmarkedIds = DB::table('community_post_bookmarks')
            ->where('user_id', $viewer->id)
            ->whereIn('post_id', $posts->pluck('id'))
            ->pluck('post_id')
            ->all();

        $postsData = $posts->map(function (CommunityPost $p) use ($viewer, $viewerLikedIds, $viewerBookmarkedIds, $viewerProfileMediaLikedIds, $userSection) {
            $mediaItems = $p->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => PublicFileUrl::resolve($m->media_url),
                'type' => $m->media_type,
                'name' => $m->file_name,
                'size' => $m->file_size,
                'likes_count' => (int) $m->likes_count,
                'is_liked' => in_array($m->id, $viewerProfileMediaLikedIds, true),
                'comments_count' => (int) $m->comments_count,
                'comments' => $m->comments->map(fn (CommunityComment $comment) => $this->serializeComment($comment, $viewer, $userSection))->values()->all(),
            ])->values()->all();

            if (empty($mediaItems) && $p->attachment_url) {
                $mediaItems = [[
                    'id' => 0,
                    'url' => PublicFileUrl::resolve($p->attachment_url),
                    'type' => $p->attachment_type,
                    'name' => $p->attachment_name,
                    'size' => $p->attachment_size,
                    'likes_count' => 0,
                    'is_liked' => false,
                    'comments_count' => 0,
                    'comments' => [],
                ]];
            }

            return [
                'id' => $p->id,
                'content' => $p->content,
                'attachment_url' => $p->attachment_url ? PublicFileUrl::resolve($p->attachment_url) : null,
                'attachment_type' => $p->attachment_type,
                'attachment_name' => $p->attachment_name,
                'attachment_size' => $p->attachment_size,
                'media' => $mediaItems,
                'music' => $p->musicTrack && $p->musicTrack->is_active ? [
                    'id' => $p->musicTrack->id,
                    'title' => $p->musicTrack->title,
                    'artist' => $p->musicTrack->artist,
                    'duration' => (float) $p->musicTrack->duration_seconds,
                    'audio_url' => route('profile-music.stream', $p->musicTrack->id),
                    'cover_image_url' => $p->musicTrack->cover_image_path ? PublicFileUrl::resolve($p->musicTrack->cover_image_path) : null,
                ] : null,
                'likes_count' => (int) $p->likes_count,
                'comments_count' => (int) $p->comments_count,
                'comments' => $p->comments->map(fn (CommunityComment $comment) => $this->serializeComment($comment, $viewer, $userSection))->values()->all(),
                'created_at' => $p->created_at->diffForHumans(short: true),
                'is_liked' => in_array($p->id, $viewerLikedIds, true),
                'is_bookmarked' => in_array($p->id, $viewerBookmarkedIds, true),
                'is_anonymous' => (bool) $p->is_anonymous,
                'section_name' => $p->section?->name ?? ($userSection?->name ?? 'Section'),
            ];
        })->all();

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
                ->with([
                    'user:id,public_id,name,avatar',
                    'section:id,name',
                    'media' => fn ($q) => $q->withExists(['likes as is_liked' => fn ($likes) => $likes->where('user_id', $viewer->id)]),
                    'musicTrack',
                    ...$this->commentRelations($viewer),
                ])
                ->withExists(['likes as is_liked' => fn ($q) => $q->where('user_id', $viewer->id)])
                ->latest()
                ->limit(50)
                ->get();

            $savedPosts = $saved->map(function (CommunityPost $p) use ($viewer, $userSection) {
                $mediaItems = $p->media->map(fn ($m) => [
                    'id' => $m->id,
                    'url' => PublicFileUrl::resolve($m->media_url),
                    'type' => $m->media_type,
                    'name' => $m->file_name,
                    'size' => $m->file_size,
                    'likes_count' => (int) $m->likes_count,
                    'is_liked' => (bool) $m->is_liked,
                    'comments_count' => (int) $m->comments_count,
                    'comments' => $m->comments->map(fn (CommunityComment $comment) => $this->serializeComment($comment, $viewer, $userSection))->values()->all(),
                ])->values()->all();

                if (empty($mediaItems) && $p->attachment_url) {
                    $mediaItems = [[
                        'id' => 0,
                        'url' => PublicFileUrl::resolve($p->attachment_url),
                        'type' => $p->attachment_type,
                        'name' => $p->attachment_name,
                        'size' => $p->attachment_size,
                        'likes_count' => 0,
                        'is_liked' => false,
                        'comments_count' => 0,
                        'comments' => [],
                    ]];
                }

                return [
                    'id' => $p->id,
                    'content' => $p->content,
                    'attachment_url' => $p->attachment_url ? PublicFileUrl::resolve($p->attachment_url) : null,
                    'attachment_type' => $p->attachment_type,
                    'attachment_name' => $p->attachment_name,
                    'attachment_size' => $p->attachment_size,
                    'media' => $mediaItems,
                    'music' => $p->musicTrack && $p->musicTrack->is_active ? [
                        'id' => $p->musicTrack->id,
                        'title' => $p->musicTrack->title,
                        'artist' => $p->musicTrack->artist,
                        'duration' => (float) $p->musicTrack->duration_seconds,
                        'audio_url' => route('profile-music.stream', $p->musicTrack->id),
                        'cover_image_url' => $p->musicTrack->cover_image_path ? PublicFileUrl::resolve($p->musicTrack->cover_image_path) : null,
                    ] : null,
                    'likes_count' => (int) $p->likes_count,
                    'comments_count' => (int) $p->comments_count,
                    'comments' => $p->comments->map(fn (CommunityComment $comment) => $this->serializeComment($comment, $viewer, $userSection))->values()->all(),
                    'is_liked' => (bool) $p->is_liked,
                    'created_at' => $p->created_at->diffForHumans(short: true),
                    'author_name' => $p->user?->name ?? 'Student',
                    'author_handle' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $p->user?->name ?? 'student')),
                    'author_avatar' => $p->user?->avatar ? PublicFileUrl::resolve($p->user->avatar) : null,
                    'section_name' => $p->section?->name ?? 'Section',
                ];
            })->all();
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
    private function resolveViewerSection(?User $user, $requestedSectionId = null): ?Section
    {
        if (! $user) {
            return null;
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        if ($requestedSectionId) {
            if ($user->is_admin) {
                $section = Section::query()
                    ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                    ->whereKey($requestedSectionId)
                    ->first();
                if ($section) {
                    return $section;
                }
            } else {
                $section = $user->sections()
                    ->when($workspaceId, fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                    ->where('sections.id', $requestedSectionId)
                    ->first();
                if ($section) {
                    return $section;
                }
            }
        }

        if ($user->is_admin) {
            return $user->sections()
                ->when($workspaceId, fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                ->first()
                ?? Section::query()
                    ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                    ->first();
        }

        return $user->sections()
            ->when($workspaceId, fn ($q) => $q->where('sections.workspace_id', $workspaceId))
            ->first();
    }

    /**
     * Get available sections for section switcher in presence rail.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function getAvailableSections(?User $viewer, ?Workspace $workspace): array
    {
        if (! $viewer) {
            return [];
        }

        $workspaceId = $workspace?->id ?? app(WorkspaceContext::class)->id();

        if ($viewer->is_admin) {
            return Section::query()
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'name' => (string) $s->name])
                ->values()
                ->all();
        }

        return $viewer->sections()
            ->when($workspaceId, fn ($q) => $q->where('sections.workspace_id', $workspaceId))
            ->orderBy('sections.name')
            ->get(['sections.id', 'sections.name'])
            ->map(fn ($s) => ['id' => (int) $s->id, 'name' => (string) $s->name])
            ->values()
            ->all();
    }

    /**
     * Get section presence data with a 15-second RAM cache.
     * Only displays enrolled students joined in the viewer's section.
     * Over 95% of background polling calls hit memory, keeping DB CPU near 0%.
     *
     * @return array{classmates: array, onlineCount: int, totalEnrolled: int}
     */
    private function getSectionPresenceData(?Section $section, ?User $viewer): array
    {
        if (! $section) {
            return [
                'classmates' => [],
                'onlineCount' => 0,
                'totalEnrolled' => 0,
            ];
        }

        $cacheKey = "section:{$section->id}:presence_data";

        $data = Cache::remember($cacheKey, 15, function () use ($section): array {
            $members = $section->users()
                ->where('is_admin', false)
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
                'totalEnrolled' => $section->users()->where('is_admin', false)->count(),
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
        $isRestricted = CommunitySettings::isRestrictedToSection();

        $query = CommunityPost::query()
            ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
            ->with([
                'user:id,public_id,name,avatar,level',
                'section:id,name',
                'media',
                'musicTrack',
                ...$this->commentRelations($viewer),
            ])
            ->latest();

        if ($tab === 'section' && $section) {
            $query->where('section_id', $section->id);
        } elseif ($tab === 'saved' && $viewer) {
            $query->whereHas('bookmarks', fn ($q) => $q->where('user_id', $viewer->id));
            if ($isRestricted) {
                if ($section) {
                    $query->where('section_id', $section->id);
                } elseif (! $viewer->is_admin) {
                    $query->whereRaw('1 = 0');
                }
            }
        } elseif ($isRestricted) {
            if ($section) {
                $query->where('section_id', $section->id);
            } elseif (! $viewer?->is_admin) {
                $query->whereRaw('1 = 0');
            }
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

        $allMediaIds = $posts->pluck('media')->flatten()->pluck('id')->filter()->all();
        $viewerMediaLikedIds = $viewer && ! empty($allMediaIds)
            ? DB::table('community_post_media_likes')
                ->where('user_id', $viewer->id)
                ->whereIn('post_media_id', $allMediaIds)
                ->pluck('post_media_id')
                ->all()
            : [];

        return $posts->map(function (CommunityPost $post) use ($viewer, $section, $viewerSectionId, $viewerLikedIds, $viewerBookmarkedIds, $viewerFollowingIds, $viewerMediaLikedIds): array {
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

            $authorSectionName = $post->section?->name ?? 'Workspace';
            if ($post->is_anonymous || ! $isSameSection) {
                $authorSectionName = 'Anonymous';
            }

            $mediaItems = $post->media->map(fn ($m) => [
                'id' => $m->id,
                'url' => PublicFileUrl::resolve($m->media_url),
                'type' => $m->media_type,
                'name' => $m->file_name,
                'size' => $m->file_size,
                'likes_count' => (int) $m->likes_count,
                'is_liked' => in_array($m->id, $viewerMediaLikedIds, true),
                'comments_count' => (int) $m->comments_count,
                'comments' => $m->comments->map(fn (CommunityComment $comment) => $this->serializeComment($comment, $viewer, $section))->values()->all(),
            ])->values()->all();

            if (empty($mediaItems) && $post->attachment_url) {
                $mediaItems = [[
                    'id' => 0,
                    'url' => PublicFileUrl::resolve($post->attachment_url),
                    'type' => $post->attachment_type,
                    'name' => $post->attachment_name,
                    'size' => $post->attachment_size,
                    'likes_count' => 0,
                    'is_liked' => false,
                    'comments_count' => 0,
                    'comments' => [],
                ]];
            }

            $musicData = null;
            if ($post->musicTrack && $post->musicTrack->is_active) {
                $musicData = [
                    'id' => $post->musicTrack->id,
                    'title' => $post->musicTrack->title,
                    'artist' => $post->musicTrack->artist,
                    'duration' => (float) $post->musicTrack->duration_seconds,
                    'audio_url' => route('profile-music.stream', $post->musicTrack->id),
                    'cover_image_url' => $post->musicTrack->cover_image_path
                        ? PublicFileUrl::resolve($post->musicTrack->cover_image_path)
                        : null,
                ];
            }

            return [
                'id' => $post->id,
                'content' => $post->content,
                'created_at' => $post->created_at->diffForHumans(short: true),
                'attachment_url' => $post->attachment_url ? PublicFileUrl::resolve($post->attachment_url) : null,
                'attachment_type' => $post->attachment_type,
                'attachment_name' => $post->attachment_name,
                'attachment_size' => $post->attachment_size,
                'media' => $mediaItems,
                'music' => $musicData,
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
                    'section_name' => $authorSectionName,
                ],
                'comments' => $post->comments->map(fn (CommunityComment $comment) => $this->serializeComment($comment, $viewer, $section))->values()->all(),
            ];
        })->all();
    }

    /**
     * @return array<string, \Closure>
     */
    private function commentRelations(?User $viewer): array
    {
        $withComment = fn ($q) => $q
            ->with(['user' => fn ($uq) => $uq->select('id', 'public_id', 'name', 'avatar', 'level')->with('sections:id,name')])
            ->withExists(['likes as is_liked' => fn ($likes) => $likes->where('user_id', $viewer?->id)]);

        return [
            'comments' => fn ($q) => $withComment($q)
                ->whereNull('post_media_id')
                ->whereNull('parent_id')
                ->latest()
                ->limit(10)
                ->with(['replies' => $withComment]),
            'media.comments' => fn ($q) => $withComment($q)
                ->whereNull('parent_id')
                ->latest()
                ->limit(10)
                ->with(['replies' => $withComment]),
        ];
    }

    private function serializeComment(CommunityComment $comment, ?User $viewer, ?Section $section): array
    {
        $author = $comment->user;
        $isSameSection = $section && $viewer && $author && $author->sections->contains('id', $section->id);
        $isOwn = $viewer && $comment->user_id === $viewer->id;
        $isMasked = ! $isSameSection && ! $isOwn;

        return [
            'id' => $comment->id,
            'post_id' => $comment->post_id,
            'post_media_id' => $comment->post_media_id,
            'parent_id' => $comment->parent_id,
            'content' => $comment->content,
            'created_at' => $comment->created_at->diffForHumans(short: true),
            'author_id' => (string) ($author?->id ?? ''),
            'author_public_id' => $isMasked ? null : (string) ($author?->public_id ?? ''),
            'author_name' => $isMasked ? 'Student #'.substr(md5((string) $comment->user_id), 0, 4) : ($author?->name ?? 'Student'),
            'author_handle' => $isMasked ? 'student.'.substr(md5((string) $comment->user_id), 0, 4) : strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $author?->name ?? 'student')),
            'author_avatar' => $isMasked ? null : ($author?->avatar ? PublicFileUrl::resolve($author->avatar) : null),
            'is_masked' => $isMasked,
            'section_name' => $isMasked ? 'Anonymous' : ($section?->name ?? 'Your Section'),
            'likes_count' => (int) $comment->likes_count,
            'is_liked' => (bool) $comment->is_liked,
            'replies' => $comment->relationLoaded('replies')
                ? $comment->replies
                    ->filter(fn (CommunityComment $reply): bool => $reply->post_id === $comment->post_id && $reply->post_media_id === $comment->post_media_id)
                    ->map(fn (CommunityComment $reply): array => $this->serializeComment($reply, $viewer, $section))
                    ->values()->all()
                : [],
        ];
    }

    /**
     * Get trending hashtags from the visible feed posts.
     *
     * @param  array<int, array<string, mixed>>  $posts
     * @return array<int, array{tag: string, name: string, count: int}>
     */
    private function getTrendingTopics(array $posts): array
    {
        $tagCounts = [];
        $tagDisplayNames = [];

        foreach ($posts as $post) {
            $content = (string) ($post['content'] ?? '');
            if ($content !== '' && preg_match_all('/#([a-zA-Z0-9_]+)/', $content, $matches)) {
                $seenInPost = [];
                foreach ($matches[1] as $rawTag) {
                    $lower = strtolower($rawTag);
                    if (isset($seenInPost[$lower])) {
                        continue;
                    }
                    $seenInPost[$lower] = true;
                    $tagCounts[$lower] = ($tagCounts[$lower] ?? 0) + 1;
                    if (! isset($tagDisplayNames[$lower])) {
                        $tagDisplayNames[$lower] = $rawTag;
                    }
                }
            }
        }

        if (empty($tagCounts)) {
            return [];
        }

        arsort($tagCounts);
        $result = [];
        foreach (array_slice($tagCounts, 0, 5, true) as $lowerTag => $count) {
            $result[] = [
                'tag' => $lowerTag,
                'name' => $tagDisplayNames[$lowerTag],
                'count' => $count,
            ];
        }

        return $result;
    }

    /**
     * Get active stories grouped by user for the stories tray.
     */
    private function getActiveStoriesTray(?User $viewer, ?Workspace $workspace, ?Section $section): array
    {
        if (! $viewer) {
            return [];
        }

        if (! CommunitySettings::storiesEnabled()) {
            return [];
        }

        $viewerSectionIds = $viewer->sections()->pluck('sections.id')->all();
        $isRestricted = CommunitySettings::isRestrictedToSection();

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

            if ($isRestricted && ! $viewer->is_admin && ! $sharesSection && ! $isSelf) {
                continue;
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
