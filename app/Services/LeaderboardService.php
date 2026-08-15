<?php

namespace App\Services;

use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.1 — Extract the leaderboard from the duplicated dashboard + API
 * closures.
 *
 * Previously the dashboard closure and api/leaderboard endpoint each had their
 * own copy of the leaderboard logic, and they had already diverged (different
 * weeklyXp cast, missing xpProgress/trend in the API version).
 *
 * This service uses the dashboard version's shape as canonical (it's the
 * superset) and computes ranks with a single windowed query instead of the
 * per-section correlated subquery.
 */
class LeaderboardService
{
    /**
     * Build leaderboard data for every section the user belongs to in a given
     * season.
     *
     * @return array<int, array{sectionId: int, sectionName: string, users: Collection, userRank: int, totalPlayers: int}>
     */
    public function forUserSections(User $user, ?Season $season): array
    {
        if (! $season) {
            return [];
        }

        $userSections = $user->sections()
            ->wherePivot('season_id', $season->id)
            ->get();

        if ($userSections->isEmpty()) {
            return [];
        }

        $sectionIds = $userSections->pluck('id');

        // ── Batch-load every section's roster in one round trip ──────
        // This previously ran inside the foreach below (and again when
        // building $allUserIds), so a student in 4 sections paid 8 queries
        // just to assemble the dashboard leaderboard. Eager loading here makes
        // it 2 queries regardless of how many sections the user is in.
        //
        // sectionProgress is scoped to the sections in play so a user enrolled
        // in many sections doesn't drag in unrelated progress rows; it is then
        // matched per section in PHP.
        $userSections->load([
            'users' => fn ($q) => $q->where('is_admin', false)
                ->with(['sectionProgress' => fn ($p) => $p->whereIn('section_id', $sectionIds)]),
        ]);

        // ── Single-pass rank query (windowed) ────────────────────────
        // Replaces the per-section foreach subquery with one query using
        // DENSE_RANK() windowed across sections so students with equal XP share the same rank.
        // SQLite + Postgres both support this.
        $ranks = DB::table('section_progress')
            ->join('section_user', function ($join) {
                $join->on('section_progress.user_id', '=', 'section_user.user_id')
                    ->on('section_progress.section_id', '=', 'section_user.section_id');
            })
            ->join('users', 'section_progress.user_id', '=', 'users.id')
            ->whereIn('section_progress.section_id', $sectionIds)
            ->where('users.is_admin', false)
            ->select(
                'section_progress.section_id',
                'section_progress.user_id',
                'section_progress.exp',
                DB::raw('DENSE_RANK() OVER (PARTITION BY section_progress.section_id ORDER BY section_progress.exp DESC) as rank')
            )
            ->get()
            ->groupBy('section_id');

        $totalCounts = DB::table('section_progress')
            ->join('users', 'section_progress.user_id', '=', 'users.id')
            ->whereIn('section_progress.section_id', $sectionIds)
            ->where('users.is_admin', false)
            ->select('section_id', DB::raw('COUNT(*) as total'))
            ->groupBy('section_id')
            ->pluck('total', 'section_id');

        // ── Weekly XP + trend — one query over the last 14 days of XP ──
        // gamification_histories is the canonical XP log (exams, daily claims,
        // admin adjustments, enrollment). course_user.xp_earned is not kept in
        // sync by the live lesson flow, so it is a poor source for activity.
        //
        // Reuses the rosters eager-loaded above — no extra query per section.
        $allUserIds = $userSections
            ->flatMap(fn ($s) => $s->users->pluck('id'))
            ->unique()
            ->values();

        $thisWeekStart = now()->subDays(7);
        $prevWeekStart = now()->subDays(14);

        // Single-pass conditional aggregation for both week buckets.
        // NOTE: bindings must go through selectRaw() — DB::raw() only accepts
        // the expression and would silently drop a second argument, leaving the
        // ? placeholders unbound and misaligning every subsequent binding.
        $weekStats = DB::table('gamification_histories')
            ->whereIn('user_id', $allUserIds)
            ->where('created_at', '>=', $prevWeekStart)
            ->where('amount_xp', '>', 0)
            ->selectRaw(
                'user_id, SUM(CASE WHEN created_at >= ? THEN amount_xp ELSE 0 END) as this_week, SUM(CASE WHEN created_at < ? THEN amount_xp ELSE 0 END) as prev_week',
                [$thisWeekStart, $thisWeekStart]
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // ── Build per-section leaderboards ──────────────────────────
        $sectionLeaderboards = [];

        foreach ($userSections as $section) {
            // Already in memory from the eager load above.
            $usersInSection = $section->users;

            $sectionRanks = $ranks->get($section->id, collect());
            $userRankRow = $sectionRanks->firstWhere('user_id', $user->id);
            $userRank = $userRankRow ? (int) $userRankRow->rank : 1;

            $leaderboardUsers = $usersInSection->map(function ($u) use ($weekStats, $user, $section) {
                // ⚠️ sectionProgress is eager-loaded for ALL of the viewer's
                // sections at once, so it must be matched to THIS section.
                // Taking ->first() here would show a student's XP from an
                // unrelated section.
                // Cast both sides: section_id has no model cast, and drivers
                // differ on whether integer columns come back as int or string.
                $progress = $u->sectionProgress
                    ->first(fn ($p) => (int) $p->section_id === (int) $section->id);
                $xp = $progress?->exp ?? 0;
                $level = $progress?->level ?? 1;

                $weekly = $weekStats[$u->id] ?? null;
                $thisWeek = (float) ($weekly->this_week ?? 0);
                $prevWeek = (float) ($weekly->prev_week ?? 0);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                    'xp' => (float) $xp,
                    'level' => (int) $level,
                    'xpProgress' => (int) ($xp % 100),
                    'streak' => $u->current_streak,
                    'joinedAt' => $u->created_at->format('M Y'),
                    'weeklyXp' => (int) round($thisWeek),
                    'trend' => $this->trendFor($thisWeek, $prevWeek),
                    'isCurrentUser' => $u->id === $user->id,
                    'blurred' => $u->blur_leaderboard,
                ];
            })->sortByDesc('xp')->values();

            $sectionLeaderboards[] = [
                'sectionId' => $section->id,
                'sectionName' => $section->name,
                'users' => $leaderboardUsers,
                'userRank' => $userRank,
                'totalPlayers' => (int) ($totalCounts[$section->id] ?? $usersInSection->count()),
            ];
        }

        return $sectionLeaderboards;
    }

    /**
     * Derive a weekly trend by comparing XP earned in the last 7 days against
     * the 7 days before that. A ~15% shift either way counts as movement.
     */
    protected function trendFor(float $thisWeek, float $prevWeek): string
    {
        if ($prevWeek <= 0) {
            return $thisWeek > 0 ? 'up' : 'stable';
        }

        $ratio = $thisWeek / $prevWeek;

        if ($ratio >= 1.15) {
            return 'up';
        }

        if ($ratio <= 0.85) {
            return 'down';
        }

        return 'stable';
    }
}
