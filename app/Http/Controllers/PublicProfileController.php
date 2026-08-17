<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Season;
use App\Models\SeasonProgress;
use App\Models\User;
use App\Support\PublicFileUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PublicProfileController extends Controller
{
    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        Gate::authorize('viewPublicProfile', $user);

        $canViewPrivateProgress = Gate::allows('viewPrivateProgress', $user);
        $canViewActivity = Gate::allows('viewProfileActivity', $user);
        $canInteract = Gate::allows('interactWithProfile', $user);
        $currentSeason = Season::current();

        $user->load(['sections', 'badges']);

        $viewerSectionIds = $viewer->sections()
            ->pluck('sections.id')
            ->map(fn ($id): int => (int) $id);
        $userSectionIds = $user->sections
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
        $sharedSectionIds = $viewerSectionIds->intersect($userSectionIds)->values();
        $isSameSection = $sharedSectionIds->isNotEmpty();

        $rankingSections = $canViewPrivateProgress
            ? $user->sections
            : $user->sections->whereIn('id', $sharedSectionIds);
        $visibleSections = ($canViewPrivateProgress || $user->profile_show_sections)
            ? $rankingSections
            : collect();

        $seasonalExp = 0;
        $seasonalLevel = 1;
        $userRank = 0;
        $totalPlayers = 0;

        if ($currentSeason) {
            // A public GET must never create progress rows or award badges.
            $progress = $user->seasonProgress()
                ->where('season_id', $currentSeason->id)
                ->first();
            $seasonalExp = $progress?->exp ?? 0;
            $seasonalLevel = $progress?->level ?? 1;
            $primarySectionId = $rankingSections->first()?->id;

            $rankedStudents = SeasonProgress::query()
                ->where('season_id', $currentSeason->id)
                ->whereHas('user', function ($query) use ($primarySectionId): void {
                    $query->where('is_admin', false)
                        ->when($primarySectionId, fn ($query) => $query->whereHas(
                            'sections',
                            fn ($sectionQuery) => $sectionQuery->where('sections.id', $primarySectionId),
                        ));
                });

            $userRank = (clone $rankedStudents)
                ->where('exp', '>', $seasonalExp)
                ->count() + 1;
            $totalPlayers = $rankedStudents->count();
        }

        $isFollowing = ! $user->is($viewer)
            && $viewer->following()->whereKey($user->id)->exists();

        $sectionRanks = [];
        foreach ($visibleSections as $section) {
            $userExp = DB::table('section_progress')
                ->where('user_id', $user->id)
                ->where('section_id', $section->id)
                ->value('exp') ?? 0;

            $sectionBase = DB::table('section_progress')
                ->join('users', 'users.id', '=', 'section_progress.user_id')
                ->where('section_progress.section_id', $section->id)
                ->where('users.is_admin', false);

            $sectionRanks[] = [
                'id' => $section->id,
                'name' => $section->name,
                'rank' => (clone $sectionBase)
                    ->where('section_progress.exp', '>', $userExp)
                    ->count() + 1,
                'total' => (clone $sectionBase)->count(),
            ];
        }

        $courses = [];
        if ($canViewPrivateProgress && $currentSeason) {
            $courses = $user->courses()
                ->wherePivot('season_id', $currentSeason->id)
                ->limit(24)
                ->get()
                ->map(fn ($course) => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'progress' => $course->total_lessons > 0
                        ? round(($course->pivot->completed_lessons / $course->total_lessons) * 100)
                        : 0,
                    'completedLessons' => $course->pivot->completed_lessons,
                    'totalLessons' => $course->total_lessons,
                    'xpEarned' => $course->pivot->xp_earned,
                ]);
        }

        $history = collect();
        if ($canViewActivity) {
            $history = $user->gamificationHistories()
                ->with(['section', 'season'])
                ->where('reason', '!=', 'Admin Adjustment')
                ->latest()
                ->limit(20)
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'amount_xp' => (float) $item->amount_xp,
                    'amount_points' => (float) $item->amount_points,
                    'reason' => $item->reason,
                    'description' => $item->description,
                    'date' => $item->created_at->diffForHumans(),
                    'full_date' => $item->created_at->format('M d, Y h:i A'),
                    'section' => $item->section?->name,
                ]);
        }

        $canViewAchievements = $canViewPrivateProgress || $user->profile_show_achievements;
        $badges = [];
        if ($canViewAchievements) {
            $earnedByBadge = $user->badges->keyBy('id');
            $badgeSeasonNames = Season::query()
                ->whereIn('id', $user->badges->pluck('pivot.season_id')->filter()->unique())
                ->pluck('name', 'id');

            $badges = Badge::query()
                ->orderByRaw('required_level IS NULL, required_level ASC, name ASC')
                ->limit(100)
                ->get()
                ->map(function ($badge) use ($earnedByBadge, $badgeSeasonNames): array {
                    $earned = $earnedByBadge->has($badge->id);
                    $pivot = $earnedByBadge->get($badge->id)?->pivot;

                    return [
                        'id' => $badge->id,
                        'name' => $badge->name,
                        'description' => $badge->description,
                        'requiredLevel' => $badge->required_level,
                        'image' => $badge->image_path ? Storage::disk('public')->url($badge->image_path) : null,
                        'iconUrl' => $badge->icon_url,
                        'earned' => $earned,
                        'earnedSeason' => $earned && $pivot?->season_id
                            ? ($badgeSeasonNames[$pivot->season_id] ?? 'Unknown Season')
                            : null,
                        'earnedAt' => $earned && $pivot?->created_at
                            ? $pivot->created_at->format('M d, Y')
                            : null,
                    ];
                })->values()->all();
        }

        $canViewSocial = $canViewPrivateProgress || ($user->profile_show_social ?? true);
        $kudos = collect();
        $viewerKudo = null;
        $recentKudos = [];
        $followers = [];
        $following = [];
        $followersCount = 0;
        $followingCount = 0;

        if ($canViewSocial) {
            $kudos = DB::table('profile_kudos')
                ->where('recipient_id', $user->id)
                ->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type');
            $viewerKudo = DB::table('profile_kudos')
                ->where('sender_id', $viewer->id)
                ->where('recipient_id', $user->id)
                ->value('type');

            $visiblePeerIds = $canViewPrivateProgress
                ? null
                : User::query()
                    ->where('is_admin', false)
                    ->whereHas('sections', fn ($query) => $query->whereIn('sections.id', $sharedSectionIds))
                    ->select('users.id');

            $recentKudosQuery = DB::table('profile_kudos')
                ->join('users', 'users.id', '=', 'profile_kudos.sender_id')
                ->where('profile_kudos.recipient_id', $user->id)
                ->when($visiblePeerIds, fn ($query) => $query->whereIn('users.id', clone $visiblePeerIds))
                ->latest('profile_kudos.updated_at')
                ->limit(6);

            $recentKudos = $recentKudosQuery
                ->get(['users.public_id', 'users.name', 'users.avatar', 'profile_kudos.type', 'profile_kudos.updated_at'])
                ->map(fn ($kudo) => [
                    'id' => (string) $kudo->public_id,
                    'name' => $kudo->name,
                    'avatar' => PublicFileUrl::resolve($kudo->avatar),
                    'type' => $kudo->type,
                    'date' => $kudo->updated_at ? Carbon::parse($kudo->updated_at)->diffForHumans() : null,
                ])->values()->all();

            $followersQuery = $user->followers()
                ->when(! $canViewPrivateProgress, fn ($query) => $this->scopeToSections($query, $sharedSectionIds));
            $followingQuery = $user->following()
                ->when(! $canViewPrivateProgress, fn ($query) => $this->scopeToSections($query, $sharedSectionIds));

            $followersCount = (clone $followersQuery)->count();
            $followingCount = (clone $followingQuery)->count();

            $followers = $followersQuery
                ->orderByDesc('user_follows.created_at')
                ->limit(30)
                ->get(['users.public_id', 'users.name', 'users.avatar'])
                ->map(fn ($follower) => [
                    'id' => (string) $follower->public_id,
                    'name' => $follower->name,
                    'avatar' => $follower->avatar,
                ])->values()->all();
            $following = $followingQuery
                ->orderByDesc('user_follows.created_at')
                ->limit(30)
                ->get(['users.public_id', 'users.name', 'users.avatar'])
                ->map(fn ($followed) => [
                    'id' => (string) $followed->public_id,
                    'name' => $followed->name,
                    'avatar' => $followed->avatar,
                ])->values()->all();
        }

        return Inertia::render('User/PublicProfile', [
            'profileUser' => [
                'id' => (string) $user->public_id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'cover_photo' => $user->cover_photo,
                'bio' => $user->bio,
                'sections' => $visibleSections->pluck('name')->values()->all(),
                'sectionsHidden' => ! $canViewPrivateProgress && ! ($user->profile_show_sections ?? true),
                'streak' => $user->current_streak ?? 0,
                'joinedAt' => $user->created_at?->format('M Y') ?? 'Unknown',
                'isCurrentUser' => $user->is($viewer),
            ],
            'stats' => [
                'level' => $seasonalLevel,
                'xp' => $seasonalExp,
                'xpProgress' => (int) ($seasonalExp % 100),
                'rank' => $userRank,
                'totalPlayers' => $totalPlayers,
                'badgesCount' => $canViewAchievements ? $user->badges->count() : 0,
                'followersCount' => $followersCount,
                'followingCount' => $followingCount,
            ],
            'history' => $history,
            'badges' => $badges,
            'sectionRanks' => $sectionRanks,
            'courses' => $courses,
            'isSameSection' => $isSameSection,
            'privacyControlsEnabled' => true,
            'canViewActivity' => $canViewActivity,
            'canViewPrivateProgress' => $canViewPrivateProgress,
            'canViewAchievements' => $canViewAchievements,
            'canViewSocial' => $canViewSocial,
            'canInteract' => $canInteract,
            'isFollowing' => $isFollowing,
            'kudos' => [
                'great-work' => (int) ($kudos['great-work'] ?? 0),
                'on-fire' => (int) ($kudos['on-fire'] ?? 0),
                'keep-going' => (int) ($kudos['keep-going'] ?? 0),
            ],
            'viewerKudo' => $viewerKudo,
            'recentKudos' => $recentKudos,
            'followers' => $followers,
            'following' => $following,
        ]);
    }

    private function scopeToSections($query, $sectionIds)
    {
        return $query->whereHas(
            'sections',
            fn ($sectionQuery) => $sectionQuery->whereIn('sections.id', $sectionIds),
        );
    }
}
