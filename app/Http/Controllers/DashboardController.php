<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Season;
use App\Services\BadgeAwardService;
use App\Services\LeaderboardService;
use App\Services\StreakService;
use App\Services\UpcomingExamsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.3 — Extract the 230-line dashboard closure into a controller.
 *
 * Target: under 40 lines by composing LeaderboardService, StreakService,
 * UpcomingExamsService, and keeping only view-specific wiring here.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected StreakService $streakService,
        protected LeaderboardService $leaderboardService,
        protected UpcomingExamsService $upcomingExamsService,
        protected BadgeAwardService $badgeAwardService,
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        $currentSeason = Season::current();

        // ── Streak ─────────────────────────────────────────────────
        $this->streakService->touch($user);

        // ── Activity / Login Dates for Heatmap (last 90 days) ────
        $loginDates = DB::table('gamification_histories')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('DATE(created_at) as d')
            ->distinct()
            ->pluck('d')
            ->map(fn ($d) => (string) $d)
            ->values();

        if ($user->last_login_at && $user->last_login_at->isToday()) {
            $today = now()->toDateString();
            if (! $loginDates->contains($today)) {
                $loginDates->push($today);
            }
        }

        // ── Seasonal Progress ──────────────────────────────────────
        $seasonalProgress = $user->activeSeasonProgress();
        $seasonalExp = $seasonalProgress?->exp ?? 0;
        $seasonalLevel = $seasonalProgress?->level ?? 1;
        $seasonalPoints = $seasonalProgress?->points ?? 0;

        $this->badgeAwardService->awardEligibleBadges(
            $user,
            (int) $seasonalLevel,
            $currentSeason?->id
        );

        $earnedBadges = $user->badges()
            ->orderByPivot('created_at', 'desc')
            ->get();

        $badgeSeasonNames = Season::query()
            ->whereIn('id', $earnedBadges->pluck('pivot.season_id')->filter()->unique())
            ->pluck('name', 'id');

        $availableSeasonModels = Season::query()
            ->whereIn('id', DB::table('section_user')
                ->where('user_id', $user->id)
                ->whereNotNull('season_id')
                ->distinct()
                ->pluck('season_id')
            )
            ->orderBy('start_date', 'desc')
            ->get();

        $availableSeasons = $availableSeasonModels
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])
            ->values()
            ->all();

        $initialSeason = $availableSeasonModels->first() ?? $currentSeason;

        $sectionIds = $initialSeason
            ? $user->sections()->wherePivot('season_id', $initialSeason->id)->pluck('sections.id')
            : $user->sections()->pluck('sections.id');

        // ── Announcements ──────────────────────────────────────────
        $announcements = Announcement::where('is_active', true)->get();

        // ── Courses ────────────────────────────────────────────────
        $coursesResource = $user->courses();
        if ($initialSeason) {
            $coursesResource->wherePivot('season_id', $initialSeason->id);
        }
        $courses = $coursesResource->get()->map(function ($course) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'progress' => $course->total_lessons > 0
                    ? round(($course->pivot->completed_lessons / $course->total_lessons) * 100)
                    : 0,
                'completedLessons' => $course->pivot->completed_lessons,
                'totalLessons' => $course->total_lessons,
                'xpEarned' => $course->pivot->xp_earned,
                'nextDeadline' => $course->pivot->next_deadline ?? 'To be announced',
            ];
        });

        // ── Assignments ────────────────────────────────────────────
        $assignments = $user->assignments()->get()->map(function ($assignment) {
            $due = $assignment->due_date ? Carbon::parse($assignment->due_date) : null;

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'dueDate' => $due ? $due->format('M d, Y') : 'No deadline',
                'dueAtIso' => $due?->toIso8601String(),
                'isOverdue' => $due ? $due->isPast() : false,
                'submitted' => (bool) $assignment->pivot->submitted,
                'status' => $assignment->pivot->status,
                'grade' => $assignment->pivot->grade,
            ];
        });

        // ── Upcoming Exams ─────────────────────────────────────────
        $upcomingExams = $this->upcomingExamsService->forUser($user, $sectionIds);

        // ── Leaderboard ────────────────────────────────────────────
        $sectionLeaderboards = $this->leaderboardService->forUserSections($user, $initialSeason);

        return inertia('Dashboard', [
            'userStats' => [
                'totalXP' => $seasonalExp,
                'level' => $seasonalLevel,
                'currentXP' => $seasonalExp % 100,
                'maxXPForLevel' => 100,
                'rank' => 'Player',
                'rankNumber' => count($sectionLeaderboards) > 0 ? $sectionLeaderboards[0]['userRank'] : 0,
                'totalPlayers' => count($sectionLeaderboards) > 0 ? $sectionLeaderboards[0]['totalPlayers'] : 0,
                'achievements' => $earnedBadges->count(),
                'points' => $seasonalPoints,
                'streak' => $user->current_streak,
                'longestStreak' => (int) ($user->longest_streak ?? 0),
                'joinedAt' => $user->created_at->format('M Y'),
            ],
            'userBadges' => $earnedBadges->map(fn ($badge) => [
                'id' => $badge->id,
                'name' => $badge->name,
                'description' => $badge->description,
                'requiredLevel' => $badge->required_level,
                'image' => $badge->image_path ? asset('storage/'.$badge->image_path) : null,
                'iconUrl' => $badge->icon_url,
                'earnedSeason' => $badge->pivot->season_id
                    ? ($badgeSeasonNames[$badge->pivot->season_id] ?? 'Unknown Season')
                    : null,
                'earnedAt' => optional($badge->pivot->created_at)?->format('M d, Y'),
            ])->values(),
            'loginDates' => $loginDates,
            'announcements' => $announcements,
            'courses' => $courses,
            'assignments' => $assignments,
            'upcomingExams' => $upcomingExams,
            'sectionLeaderboards' => $sectionLeaderboards,
            'activeSeason' => $currentSeason ? [
                'id' => $currentSeason->id,
                'name' => $currentSeason->name,
                'startDate' => $currentSeason->start_date?->toIso8601String(),
                'endDate' => $currentSeason->end_date?->toIso8601String(),
            ] : null,
            'sectionName' => $user->sections->pluck('name')->join(', '),
            'availableSeasons' => $availableSeasons,
        ]);
    }
}
