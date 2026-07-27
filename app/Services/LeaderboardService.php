<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Season;
use App\Models\SectionProgress;
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

        // ── Single-pass rank query (windowed) ────────────────────────
        // Replaces the per-section foreach subquery with one query using
        // ROW_NUMBER() windowed across sections. SQLite + Postgres both
        // support this.
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
                DB::raw('ROW_NUMBER() OVER (PARTITION BY section_progress.section_id ORDER BY section_progress.exp DESC) as rank')
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

        // ── Weekly XP — one query for all users across all sections ──
        $allUserIds = $userSections->flatMap(fn ($s) => $s->users()
            ->where('is_admin', false)
            ->pluck('users.id')
        )->unique();

        $weeklyXpMap = DB::table('course_user')
            ->whereIn('user_id', $allUserIds)
            ->where('updated_at', '>=', now()->subDays(7))
            ->select('user_id', DB::raw('SUM(xp_earned) as total'))
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        // ── Build per-section leaderboards ──────────────────────────
        $sectionLeaderboards = [];

        foreach ($userSections as $section) {
            $usersInSection = $section->users()
                ->where('is_admin', false)
                ->with(['sectionProgress' => fn ($q) => $q->where('section_id', $section->id)])
                ->get();

            $sectionRanks = $ranks->get($section->id, collect());
            $userRankRow = $sectionRanks->firstWhere('user_id', $user->id);
            $userRank = $userRankRow ? (int) $userRankRow->rank : 1;

            $leaderboardUsers = $usersInSection->map(function ($u) use ($weeklyXpMap, $user) {
                $progress = $u->sectionProgress->first();
                $xp = $progress?->exp ?? 0;
                $level = $progress?->level ?? 1;

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar' => $u->avatar,
                    'xp' => (float) $xp,
                    'level' => (int) $level,
                    'xpProgress' => (int) ($xp % 100),
                    'streak' => $u->current_streak,
                    'joinedAt' => $u->created_at->format('M Y'),
                    'weeklyXp' => (int) ($weeklyXpMap[$u->id] ?? 0),
                    'trend' => 'stable',
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
}
