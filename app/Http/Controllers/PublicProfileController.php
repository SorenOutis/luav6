<?php

namespace App\Http\Controllers;

use App\Models\Badge;
use App\Models\Season;
use App\Models\SeasonProgress;
use App\Models\User;
use App\Services\BadgeAwardService;
use App\Support\PublicFileUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class PublicProfileController extends Controller
{
    public function show(Request $request, User $user)
    {
        $viewer = $request->user();
        $currentSeason = Season::current();

        $user->load(['sections', 'badges']);

        $seasonalExp = 0;
        $seasonalLevel = 1;
        $userRank = 0;
        $totalPlayers = 0;

        if ($currentSeason) {
            $progress = $user->activeSeasonProgress();
            $seasonalExp = $progress?->exp ?? 0;
            $seasonalLevel = $progress?->level ?? 1;

            if ($user->is($viewer)) {
                app(BadgeAwardService::class)->awardEligibleBadges(
                    $user,
                    (int) $seasonalLevel,
                    $currentSeason->id
                );
                $user->load('badges');
            }

            $primarySectionId = $user->sections()->first()?->id;

            $userRank = SeasonProgress::where('season_id', $currentSeason->id)
                ->whereHas('user', function ($q) use ($primarySectionId) {
                    $q->where('is_admin', false);
                    if ($primarySectionId) {
                        $q->whereHas('sections', function ($sq) use ($primarySectionId) {
                            $sq->where('sections.id', $primarySectionId);
                        });
                    }
                })
                ->where('exp', '>', $seasonalExp)
                ->count() + 1;

            $totalPlayers = SeasonProgress::where('season_id', $currentSeason->id)
                ->whereHas('user', function ($q) use ($primarySectionId) {
                    $q->where('is_admin', false);
                    if ($primarySectionId) {
                        $q->whereHas('sections', function ($sq) use ($primarySectionId) {
                            $sq->where('sections.id', $primarySectionId);
                        });
                    }
                })
                ->count();
        }

        $viewerSectionIds = $viewer->sections()->pluck('sections.id')->toArray();
        $userSectionIds = $user->sections()->pluck('sections.id')->toArray();
        $sharedSections = array_intersect($viewerSectionIds, $userSectionIds);
        $isSameSection = ! empty($sharedSections);
        $isFollowing = ! $user->is($viewer)
            && $viewer->following()->whereKey($user->id)->exists();

        // ── Per-section ranking (for the "Rank #N in Section" card) ──
        // Matches the leaderboard's "ties share a rank" logic: the rank is the
        // number of non-admin students in the same section with strictly more
        // XP, plus one.
        $sectionRanks = [];
        foreach ($user->sections as $section) {
            $userExp = DB::table('section_progress')
                ->where('user_id', $user->id)
                ->where('section_id', $section->id)
                ->value('exp') ?? 0;

            $sectionBase = DB::table('section_progress')
                ->join('users', 'users.id', '=', 'section_progress.user_id')
                ->where('section_progress.section_id', $section->id)
                ->where('users.is_admin', false);

            $total = (clone $sectionBase)->count();
            $better = (clone $sectionBase)->where('section_progress.exp', '>', $userExp)->count();

            $sectionRanks[] = [
                'id' => $section->id,
                'name' => $section->name,
                'rank' => $better + 1,
                'total' => $total,
            ];
        }

        $courses = [];
        if ($isSameSection && $currentSeason) {
            $courses = $user->courses()
                ->wherePivot('season_id', $currentSeason->id)
                ->get()
                ->map(fn ($course) => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'progress' => $course->total_lessons > 0 ? round(($course->pivot->completed_lessons / $course->total_lessons) * 100) : 0,
                    'completedLessons' => $course->pivot->completed_lessons,
                    'totalLessons' => $course->total_lessons,
                    'xpEarned' => $course->pivot->xp_earned,
                ]);
        }

        $history = $user->gamificationHistories()
            ->with(['section', 'season'])
            // Back-office manual XP/point adjustments are audit-only and
            // aren't surfaced on the student's profile.
            ->where('reason', '!=', 'Admin Adjustment')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($h) => [
                'id' => $h->id,
                'amount_xp' => (float) $h->amount_xp,
                'amount_points' => (float) $h->amount_points,
                'reason' => $h->reason,
                'description' => $h->description,
                'date' => $h->created_at->diffForHumans(),
                'full_date' => $h->created_at->format('M d, Y h:i A'),
                'section' => $h->section?->name,
            ]);

        // ── Achievements catalog ─────────────────────────────────────
        // Show the full set of badges (earned + locked) so students can see
        // what's next. Locked badges are greyed out in the UI.
        $earnedByBadge = $user->badges->keyBy('id');
        $badgeSeasonNames = Season::query()
            ->whereIn('id', $user->badges->pluck('pivot.season_id')->filter()->unique())
            ->pluck('name', 'id');

        $badges = Badge::query()
            ->orderByRaw('required_level IS NULL, required_level ASC, name ASC')
            ->get()
            ->map(function ($b) use ($earnedByBadge, $badgeSeasonNames) {
                $earned = $earnedByBadge->has($b->id);
                $pivot = $earnedByBadge->get($b->id)?->pivot;

                return [
                    'id' => $b->id,
                    'name' => $b->name,
                    'description' => $b->description,
                    'requiredLevel' => $b->required_level,
                    'image' => $b->image_path ? Storage::disk('public')->url($b->image_path) : null,
                    'iconUrl' => $b->icon_url,
                    'earned' => $earned,
                    'earnedSeason' => $earned && $pivot?->season_id
                        ? ($badgeSeasonNames[$pivot->season_id] ?? 'Unknown Season')
                        : null,
                    'earnedAt' => $earned && $pivot?->created_at
                        ? $pivot->created_at->format('M d, Y')
                        : null,
                ];
            })->values()->all();

        // ── Kudos ────────────────────────────────────────────────────
        $kudos = DB::table('profile_kudos')
            ->where('recipient_id', $user->id)
            ->selectRaw('type, count(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');
        $viewerKudo = DB::table('profile_kudos')
            ->where('sender_id', $viewer->id)
            ->where('recipient_id', $user->id)
            ->value('type');

        $recentKudos = DB::table('profile_kudos')
            ->join('users', 'users.id', '=', 'profile_kudos.sender_id')
            ->where('profile_kudos.recipient_id', $user->id)
            ->orderBy('profile_kudos.updated_at', 'desc')
            ->limit(6)
            ->get(['users.id', 'users.name', 'users.avatar', 'profile_kudos.type', 'profile_kudos.updated_at'])
            ->map(fn ($k) => [
                'id' => $k->id,
                'name' => $k->name,
                'avatar' => PublicFileUrl::resolve($k->avatar),
                'type' => $k->type,
                'date' => $k->updated_at ? Carbon::parse($k->updated_at)->diffForHumans() : null,
            ])->values()->all();

        // ── Followers / following lists (for the follower modals) ──
        $followers = $user->followers()
            ->orderBy('user_follows.created_at', 'desc')
            ->limit(30)
            ->get(['users.id', 'users.name', 'users.avatar'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'avatar' => $f->avatar,
            ])->values()->all();
        $following = $user->following()
            ->orderBy('user_follows.created_at', 'desc')
            ->limit(30)
            ->get(['users.id', 'users.name', 'users.avatar'])
            ->map(fn ($f) => [
                'id' => $f->id,
                'name' => $f->name,
                'avatar' => $f->avatar,
            ])->values()->all();

        return Inertia::render('User/PublicProfile', [
            'profileUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'cover_photo' => $user->cover_photo,
                'bio' => $user->bio,
                'sections' => $user->sections->map(fn ($s) => $s->name)->toArray(),
                'streak' => $user->current_streak ?? 0,
                'joinedAt' => $user->created_at ? $user->created_at->format('M Y') : 'Unknown',
                'isCurrentUser' => $user->id === $viewer->id,
            ],
            'stats' => [
                'level' => $seasonalLevel,
                'xp' => $seasonalExp,
                'xpProgress' => (int) ($seasonalExp % 100),
                'rank' => $userRank,
                'totalPlayers' => $totalPlayers,
                'badgesCount' => $user->badges->count(),
                'followersCount' => $user->followers()->count(),
                'followingCount' => $user->following()->count(),
            ],
            'history' => $history,
            'badges' => $badges,
            'sectionRanks' => $sectionRanks,
            'courses' => $courses,
            'isSameSection' => $isSameSection,
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
}
