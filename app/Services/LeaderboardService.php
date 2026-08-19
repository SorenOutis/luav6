<?php

namespace App\Services;

use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Support\PublicFileUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Canonical leaderboard builder used by the dashboard and leaderboard API.
 *
 * The visible roster is deliberately bounded. Rank and total-player values are
 * still calculated over the complete section by SQL window functions, while
 * only the first rows plus the viewer are hydrated and serialized.
 */
class LeaderboardService
{
    /** Maximum rows returned per section, excluding an out-of-range viewer. */
    public const MAX_VISIBLE_USERS = 100;

    /** Defensive ceiling for students enrolled in an unusually high number of sections. */
    public const MAX_VISIBLE_SECTIONS = 20;

    /** Higher ceiling for the super admin's platform-wide, per-workspace view. */
    public const MAX_ADMIN_VISIBLE_SECTIONS = 100;

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
            ->orderBy('sections.name')
            ->limit(self::MAX_VISIBLE_SECTIONS)
            ->get();

        return $this->build($user, $season, $userSections);
    }

    /**
     * Resolve the sections a viewer is allowed to inspect and build their
     * leaderboards.
     *
     * Students (and regular users) see the sections they are enrolled in for
     * the season. Super admins see every section platform-wide — grouped per
     * workspace, with the workspace name attached — or just the inspected
     * workspace's sections while workspace inspection is active (the Section
     * workspace global scope applies that constraint automatically).
     *
     * @return array<int, array{sectionId: int, sectionName: string, workspaceId?: int|null, workspaceName?: string|null, users: Collection, userRank: int, totalPlayers: int}>
     */
    public function forViewer(User $user, ?Season $season): array
    {
        if (! $season) {
            return [];
        }

        if (! $user->isSuperAdmin()) {
            return $this->forUserSections($user, $season);
        }

        $sections = Section::query()
            ->whereHas('users', fn ($query) => $query->where('section_user.season_id', $season->id))
            ->with('workspace:id,name')
            ->orderBy('sections.workspace_id')
            ->orderBy('sections.name')
            ->limit(self::MAX_ADMIN_VISIBLE_SECTIONS)
            ->get();

        return $this->build($user, $season, $sections, includeWorkspace: true);
    }

    /**
     * Rank and serialize the given sections for the given season.
     *
     * @param  Collection<int, Section>  $sections
     * @return array<int, array<string, mixed>>
     */
    protected function build(User $user, Season $season, Collection $sections, bool $includeWorkspace = false): array
    {
        if ($sections->isEmpty()) {
            return [];
        }

        $sectionIds = $sections->pluck('id')->map(fn ($id): int => (int) $id);

        $ranked = DB::table('section_user as membership')
            ->join('users', 'users.id', '=', 'membership.user_id')
            ->leftJoin('section_progress as progress', function ($join): void {
                $join->on('progress.user_id', '=', 'users.id')
                    ->on('progress.section_id', '=', 'membership.section_id');
            })
            ->whereIn('membership.section_id', $sectionIds)
            ->where('membership.season_id', $season->id)
            ->where('users.is_admin', false)
            ->select([
                'membership.section_id',
                'users.id as user_id',
                'users.public_id',
                'users.name',
                'users.avatar',
                'users.current_streak',
                'users.created_at',
                'users.blur_leaderboard',
            ])
            ->selectRaw('COALESCE(progress.exp, 0) as xp')
            ->selectRaw('COALESCE(progress.level, 1) as level')
            ->selectRaw('RANK() OVER (PARTITION BY membership.section_id ORDER BY COALESCE(progress.exp, 0) DESC) as rank_position')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY membership.section_id ORDER BY COALESCE(progress.exp, 0) DESC, users.id ASC) as row_position')
            ->selectRaw('COUNT(*) OVER (PARTITION BY membership.section_id) as total_players');

        // ROW_NUMBER provides a hard payload/memory ceiling even when hundreds
        // of students tie on XP. The viewer is included separately so their
        // "Your rank" card remains useful when they are outside the top rows.
        $visibleRows = DB::query()
            ->fromSub($ranked, 'ranked_users')
            ->where(function ($query) use ($user): void {
                $query->where('row_position', '<=', self::MAX_VISIBLE_USERS)
                    ->orWhere('user_id', $user->id);
            })
            ->orderBy('section_id')
            ->orderBy('row_position')
            ->get();

        $rowsBySection = $visibleRows->groupBy(fn ($row): int => (int) $row->section_id);
        $visibleUserIds = $visibleRows->pluck('user_id')->map(fn ($id): int => (int) $id)->unique()->values();

        $thisWeekStart = now()->subDays(7);
        $prevWeekStart = now()->subDays(14);

        $weekStats = DB::table('gamification_histories')
            ->whereIn('user_id', $visibleUserIds)
            ->where('created_at', '>=', $prevWeekStart)
            ->where('amount_xp', '>', 0)
            ->selectRaw(
                'user_id, SUM(CASE WHEN created_at >= ? THEN amount_xp ELSE 0 END) as this_week, SUM(CASE WHEN created_at < ? THEN amount_xp ELSE 0 END) as prev_week',
                [$thisWeekStart, $thisWeekStart]
            )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        return $sections->map(function ($section) use ($rowsBySection, $weekStats, $user, $includeWorkspace) {
            $rows = $rowsBySection->get((int) $section->id, collect());

            $leaderboardUsers = $rows->map(function ($row) use ($weekStats, $user) {
                $weekly = $weekStats[(int) $row->user_id] ?? null;
                $thisWeek = (float) ($weekly->this_week ?? 0);
                $prevWeek = (float) ($weekly->prev_week ?? 0);
                $xp = (float) $row->xp;

                return [
                    'id' => (int) $row->user_id,
                    'publicId' => (string) $row->public_id,
                    'name' => (string) $row->name,
                    'avatar' => PublicFileUrl::resolve($row->avatar),
                    'xp' => $xp,
                    'level' => (int) $row->level,
                    'xpProgress' => (int) fmod($xp, 100),
                    'streak' => (int) $row->current_streak,
                    'joinedAt' => Carbon::parse($row->created_at)->format('M Y'),
                    'weeklyXp' => (int) round($thisWeek),
                    'trend' => $this->trendFor($thisWeek, $prevWeek),
                    'isCurrentUser' => (int) $row->user_id === (int) $user->id,
                    'blurred' => filter_var($row->blur_leaderboard, FILTER_VALIDATE_BOOL),
                ];
            })->values();

            $viewer = $rows->first(fn ($row): bool => (int) $row->user_id === (int) $user->id);
            $first = $rows->first();

            $leaderboard = [
                'sectionId' => (int) $section->id,
                'sectionName' => (string) $section->name,
                'users' => $leaderboardUsers,
                'userRank' => (int) ($viewer->rank_position ?? 0),
                'totalPlayers' => (int) ($first->total_players ?? 0),
                'isTruncated' => (int) ($first->total_players ?? 0) > $leaderboardUsers->count(),
            ];

            if ($includeWorkspace) {
                $leaderboard['workspaceId'] = $section->workspace_id !== null ? (int) $section->workspace_id : null;
                $leaderboard['workspaceName'] = $section->workspace?->name;
            }

            return $leaderboard;
        })->values()->all();
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
