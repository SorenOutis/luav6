<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Season;
use App\Models\Section;
use App\Services\BadgeAwardService;
use App\Services\BonusXpService;
use App\Services\ClaimXpService;
use App\Services\LeaderboardService;
use App\Services\StreakService;
use App\Services\UpcomingExamsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 3.3 — Extract the 230-line dashboard closure into a controller.
 *
 * Target: under 40 lines by composing LeaderboardService, StreakService,
 * UpcomingExamsService, and keeping only view-specific wiring here.
 */
class DashboardController extends Controller
{
    private const ANNOUNCEMENT_PREVIEW_LIMIT = 10;

    private const COURSE_PREVIEW_LIMIT = 12;

    private const BADGE_PREVIEW_LIMIT = 12;

    private const SEASON_OPTION_LIMIT = 20;

    public function __construct(
        protected StreakService $streakService,
        protected LeaderboardService $leaderboardService,
        protected UpcomingExamsService $upcomingExamsService,
        protected BadgeAwardService $badgeAwardService,
        protected ClaimXpService $claimXpService,
        protected BonusXpService $bonusXpService,
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

        $earnedBadgeCount = $user->badges()->count();
        $earnedBadges = $user->badges()
            ->orderByPivot('created_at', 'desc')
            ->limit(self::BADGE_PREVIEW_LIMIT)
            ->get();

        $badgeSeasonNames = Season::query()
            ->whereIn('id', $earnedBadges->pluck('pivot.season_id')->filter()->unique())
            ->pluck('name', 'id');

        // Students pick from the seasons their own enrollments point to;
        // super admins from every season with enrollments, so the dashboard
        // leaderboard can cover all sections per workspace. The Section and
        // Season workspace global scopes confine the super admin variant to
        // the inspected workspace while inspection is active.
        $availableSeasonModels = $user->isSuperAdmin()
            ? Season::query()
                ->whereIn('id', Section::query()
                    ->join('section_user', 'section_user.section_id', '=', 'sections.id')
                    ->whereNotNull('section_user.season_id')
                    ->distinct()
                    ->select('section_user.season_id')
                )
                ->orderBy('start_date', 'desc')
                ->limit(self::SEASON_OPTION_LIMIT)
                ->get()
            : Season::query()
                ->whereIn('id', DB::table('section_user')
                    ->where('user_id', $user->id)
                    ->whereNotNull('season_id')
                    ->distinct()
                    ->pluck('season_id')
                )
                ->orderBy('start_date', 'desc')
                ->limit(self::SEASON_OPTION_LIMIT)
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
        // Section-targeted announcements are only shown to students
        // enrolled in that section; announcements without a section
        // are shown to everyone.
        $studentSectionIds = $user->sections->pluck('id');

        $announcements = Announcement::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query
                ->whereNull('section_id')
                ->orWhereIn('section_id', $studentSectionIds))
            ->with('section:id,name')
            ->latest()
            ->limit(self::ANNOUNCEMENT_PREVIEW_LIMIT)
            ->get()
            ->map(fn (Announcement $announcement) => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'description' => $announcement->description,
                'link' => $announcement->link,
                'sectionName' => $announcement->section?->name,
                'createdAt' => $announcement->created_at?->diffForHumans(),
            ])
            ->values();

        // ── Courses ────────────────────────────────────────────────
        $coursesResource = $user->courses();
        if ($initialSeason) {
            $coursesResource->wherePivot('season_id', $initialSeason->id);
        }
        $courses = $coursesResource
            ->limit(self::COURSE_PREVIEW_LIMIT)
            ->get()
            ->map(function ($course) {
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
        // Driven by section targeting so upcoming work shows up before the
        // student has submitted anything, not only afterwards.
        $allSectionIds = $user->sections()->pluck('sections.id');
        $submissionPivots = $user->assignments()->get()->keyBy('id');

        $assignments = Assignment::query()
            ->visibleToSections($allSectionIds)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get()
            ->map(function ($assignment) use ($submissionPivots) {
                $due = $assignment->due_date;
                $pivot = $submissionPivots->get($assignment->id)?->pivot;

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'dueDate' => $due ? $due->format('M d, Y') : 'No deadline',
                    'dueAtIso' => $assignment->dueDateForClient(),
                    'isOverdue' => $due ? $due->isPast() : false,
                    'submitted' => (bool) ($pivot?->submitted ?? false),
                    'status' => $pivot?->status ?? 'Pending',
                    'grade' => $pivot?->grade,
                ];
            })
            ->values();

        // ── Upcoming Exams ─────────────────────────────────────────
        $upcomingExams = $this->upcomingExamsService->forUser($user, $sectionIds);

        // ── Leaderboard ────────────────────────────────────────────
        $sectionLeaderboards = $this->leaderboardService->forViewer($user, $initialSeason);

        // ── Daily Claim ────────────────────────────────────────────
        $canClaim = $this->claimXpService->canClaim($user);
        $claimAmount = $this->claimXpService->claimAmount($user);
        $nextClaimAt = $this->claimXpService->nextClaimAt($user);

        // ── Bonus Claim (flat, inside Level modal) ─────────────────
        $bonusCanClaim = $this->bonusXpService->canClaim($user);
        $bonusAmount = $this->bonusXpService->bonusXp();
        $bonusNextClaimAt = $this->bonusXpService->nextClaimAt($user);
        $bonusLastClaimedAt = $this->bonusXpService->lastClaimedAt($user);

        // Offer the claim popup once per calendar day instead of once per
        // session. A boolean flag set on the first dashboard visit never resets
        // for sessions that span multiple days (remember-me cookie, tab left
        // open), so on later logins/visits with a fresh daily XP available only
        // the inline claim card would render — the popup would never show again.
        // Keying the flag by date re-offers the popup on every new day (i.e. on
        // the next login) while the reward is still unclaimed.
        $promptShownOn = $request->session()->get('daily_claim_prompt_shown_on');
        $showClaimPrompt = $canClaim && $promptShownOn !== now()->toDateString();

        // Consume the per-day flag only when the prompt is actually delivered
        // now. Users without a section see the section-selection modal first,
        // so their prompt (and flag) is deferred: either the client marks it as
        // shown via api/claim-xp/prompt-shown when it opens, or it is set here
        // on the reload after they join a section.
        if ($user->sections->isNotEmpty() && $showClaimPrompt) {
            $request->session()->put('daily_claim_prompt_shown_on', now()->toDateString());
        }

        $historyQuery = $user->gamificationHistories()
            ->when($currentSeason, fn ($query) => $query->where('season_id', $currentSeason->id))
            // Back-office manual XP/point adjustments are audit-only and
            // aren't surfaced on the student's dashboard.
            ->where('reason', '!=', 'Admin Adjustment');

        // Aggregate in SQL instead of hydrating the student's complete ledger.
        // These queries stay constant in memory even after years of activity.
        $xpBreakdown = (clone $historyQuery)
            ->where('amount_xp', '!=', 0)
            ->select('reason')
            ->selectRaw('SUM(amount_xp) as total_amount, COUNT(*) as entry_count')
            ->groupBy('reason')
            ->get()
            ->map(fn ($entry) => [
                'label' => $entry->reason ?: 'Other activity',
                'amount' => (float) $entry->total_amount,
                'count' => (int) $entry->entry_count,
            ])->values();

        $pointsBreakdown = (clone $historyQuery)
            ->where('amount_points', '!=', 0)
            ->select('reason')
            ->selectRaw('SUM(amount_points) as total_amount, COUNT(*) as entry_count')
            ->groupBy('reason')
            ->get()
            ->map(fn ($entry) => [
                'label' => $entry->reason ?: 'Other activity',
                'amount' => (float) $entry->total_amount,
                'count' => (int) $entry->entry_count,
            ])->values();

        // The card is a recent-activity preview, not an unbounded ledger.
        $xpHistory = (clone $historyQuery)
            ->where('amount_xp', '!=', 0)
            ->latest('created_at')
            ->latest('id')
            ->limit(30)
            ->get(['id', 'amount_xp', 'reason', 'description', 'created_at'])
            ->map(fn ($entry) => [
                'id' => $entry->id,
                'reason' => $entry->reason,
                'description' => $entry->description,
                'amount' => (float) $entry->amount_xp,
                'createdAt' => $entry->created_at->toIso8601String(),
                'isClaim' => in_array($entry->reason, ['Daily Claim', 'Bonus Claim'], true),
            ])->values();

        return inertia('Dashboard', [
            'claimXp' => [
                'enabled' => $this->claimXpService->isEnabled(),
                'canClaim' => $canClaim,
                'amount' => $claimAmount,
                'baseXp' => $this->claimXpService->baseXp(),
                'nextClaimAt' => $nextClaimAt?->toIso8601String(),
                'lastClaimedAt' => $user->last_claimed_at?->toIso8601String(),
                'showPrompt' => $showClaimPrompt,
            ],
            'bonusXp' => [
                'enabled' => $this->bonusXpService->isEnabled(),
                'canClaim' => $bonusCanClaim,
                'amount' => $bonusAmount,
                'nextClaimAt' => $bonusNextClaimAt?->toIso8601String(),
                'lastClaimedAt' => $bonusLastClaimedAt?->toIso8601String(),
            ],
            'statsBreakdown' => [
                'xp' => $xpBreakdown,
                'points' => $pointsBreakdown,
            ],
            'xpHistory' => $xpHistory,
            'userStats' => [
                'totalXP' => $seasonalExp,
                'level' => $seasonalLevel,
                'currentXP' => $seasonalExp % 100,
                'maxXPForLevel' => 100,
                'rank' => 'Player',
                'rankNumber' => count($sectionLeaderboards) > 0 ? $sectionLeaderboards[0]['userRank'] : 0,
                'totalPlayers' => count($sectionLeaderboards) > 0 ? $sectionLeaderboards[0]['totalPlayers'] : 0,
                'achievements' => $earnedBadgeCount,
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
                'image' => $badge->image_path ? Storage::disk('public')->url($badge->image_path) : null,
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
