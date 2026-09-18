<?php

namespace App\Services;

use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PublicFileUrl;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Canonical leaderboard builder used by the dashboard, the leaderboard API
 * and the admin leaderboards page.
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

    /** Per-section row ceiling when an admin expands a section on the leaderboards page. */
    public const MAX_ADMIN_VISIBLE_USERS = 200;

    /** Maximum sections listed on the admin leaderboards page (platform-wide super admin view). */
    public const MAX_ADMIN_PAGE_SECTIONS = 500;

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
            ->where('sections.leaderboard_enabled', true)
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
     * Sections with leaderboards disabled by an admin are always excluded:
     * this powers the student dashboard, the student leaderboard page and the
     * leaderboard API, all of which mirror what students are allowed to see.
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
            ->where('sections.leaderboard_enabled', true)
            ->with('workspace:id,name')
            ->orderBy('sections.workspace_id')
            ->orderBy('sections.name')
            ->limit(self::MAX_ADMIN_VISIBLE_SECTIONS)
            ->get();

        return $this->build($user, $season, $sections, includeWorkspace: true);
    }

    /**
     * Leaderboards for the admin panel.
     *
     * Regular admins see the sections of their own workspace — the Section
     * workspace global scope applies that automatically. Super admins see
     * every section platform-wide, optionally narrowed to a single workspace
     * via $workspaceId, and workspace inspection confines them to the
     * inspected workspace as usual.
     *
     * Every section carries at most $maxVisibleUsers rows so the initial page
     * render stays light; expanding a section refetches its full board through
     * forAdminSection().
     *
     * @return array<int, array<string, mixed>>
     */
    public function forAdminSections(
        User $admin,
        ?Season $season,
        ?int $workspaceId = null,
        int $maxVisibleUsers = self::MAX_ADMIN_VISIBLE_USERS,
        int $sectionLimit = self::MAX_ADMIN_PAGE_SECTIONS,
    ): array {
        if (! $season) {
            return [];
        }

        $sections = Section::query()
            ->when(
                $workspaceId !== null && $admin->isSuperAdmin(),
                fn ($query) => $query->where('sections.workspace_id', $workspaceId),
            )
            ->whereHas('users', fn ($query) => $query->where('section_user.season_id', $season->id))
            ->with('workspace:id,name')
            ->orderBy('sections.workspace_id')
            ->orderBy('sections.name')
            ->limit($sectionLimit)
            ->get();

        return $this->build($admin, $season, $sections, includeWorkspace: true, maxVisibleUsers: $maxVisibleUsers);
    }

    /**
     * Full leaderboard for a single section — used when an admin expands a
     * section on the admin leaderboards page. Scoped like forAdminSections().
     *
     * @return array<string, mixed>
     */
    public function forAdminSection(User $admin, ?Season $season, int $sectionId): array
    {
        if (! $season) {
            return [];
        }

        $section = Section::query()
            ->with('workspace:id,name')
            ->find($sectionId);

        if (! $section) {
            return [];
        }

        $boards = $this->build(
            $admin,
            $season,
            collect([$section]),
            includeWorkspace: true,
            maxVisibleUsers: self::MAX_ADMIN_VISIBLE_USERS,
        );

        return $boards[0] ?? [];
    }

    /**
     * Total number of sections the admin leaderboards page would list, before
     * the section-limit cap applies. Used to surface truncation in the UI.
     */
    public function countAdminSections(User $admin, ?Season $season, ?int $workspaceId = null): int
    {
        if (! $season) {
            return 0;
        }

        return (int) Section::query()
            ->when(
                $workspaceId !== null && $admin->isSuperAdmin(),
                fn ($query) => $query->where('sections.workspace_id', $workspaceId),
            )
            ->whereHas('users', fn ($query) => $query->where('section_user.season_id', $season->id))
            ->count();
    }

    /**
     * Seasons selectable on a leaderboard view.
     *
     * Students pick from the seasons their own (leaderboard-enabled)
     * enrollments point to, so a season whose sections all have their
     * leaderboards hidden does not appear. Tenant admins pick from the
     * seasons used by their own workspace's sections (the Section workspace
     * global scope applies that automatically). Super admins pick from every
     * season with enrollments — optionally narrowed to one workspace — while
     * the Section and Season workspace global scopes confine that to the
     * inspected workspace while inspection is active, and leave it
     * platform-wide otherwise.
     *
     * @return Collection<int, Season>
     */
    public function availableSeasons(User $user, ?int $workspaceId = null): Collection
    {
        if ($user->isSuperAdmin()) {
            return Season::query()
                ->when($workspaceId !== null, fn ($query) => $query->where('seasons.workspace_id', $workspaceId))
                ->whereIn('id', Section::query()
                    ->when($workspaceId !== null, fn ($query) => $query->where('sections.workspace_id', $workspaceId))
                    ->join('section_user', 'section_user.section_id', '=', 'sections.id')
                    ->whereNotNull('section_user.season_id')
                    ->distinct()
                    ->select('section_user.season_id'))
                ->orderBy('start_date', 'desc')
                ->get();
        }

        if ($user->is_admin) {
            return Season::query()
                ->whereIn('id', Section::query()
                    ->join('section_user', 'section_user.section_id', '=', 'sections.id')
                    ->whereNotNull('section_user.season_id')
                    ->distinct()
                    ->select('section_user.season_id'))
                ->orderBy('start_date', 'desc')
                ->get();
        }

        return Season::query()
            ->whereIn('id', $user->sections()
                ->wherePivotNotNull('season_id')
                ->where('sections.leaderboard_enabled', true)
                ->pluck('section_user.season_id')
                ->unique())
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Workspaces that have at least one section with student enrollments,
     * optionally for a specific season. Drives the super admin's workspace
     * filter on the admin leaderboards page.
     *
     * @return Collection<int, Workspace>
     */
    public function workspacesWithEnrollments(?Season $season): Collection
    {
        return Workspace::query()
            ->whereIn('id', Section::query()
                ->whereNotNull('sections.workspace_id')
                ->join('section_user', 'section_user.section_id', '=', 'sections.id')
                ->when($season, fn ($query) => $query->where('section_user.season_id', $season->id))
                ->whereNotNull('section_user.season_id')
                ->distinct()
                ->select('sections.workspace_id'))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Rank and serialize the given sections for the given season.
     *
     * @param  Collection<int, Section>  $sections
     * @return array<int, array<string, mixed>>
     */
    protected function build(
        User $user,
        Season $season,
        Collection $sections,
        bool $includeWorkspace = false,
        int $maxVisibleUsers = self::MAX_VISIBLE_USERS,
    ): array {
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
            ->where(function ($query) use ($user, $maxVisibleUsers): void {
                $query->where('row_position', '<=', $maxVisibleUsers)
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
                'leaderboardEnabled' => (bool) ($section->leaderboard_enabled ?? true),
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
