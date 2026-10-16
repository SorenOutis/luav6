<?php

namespace App\Ai\Tools;

use App\Models\Season;
use App\Services\LeaderboardService;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LeaderboardTool implements Tool
{
    public function name(): string
    {
        return 'leaderboard';
    }

    public function description(): Stringable|string
    {
        return 'View student leaderboards: XP rankings for one section or a top-level overview across the workspace sections, optionally for a past season. Read-only.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $limit = min(max((int) ($request['limit'] ?? 10), 1), 50);
        $sectionLimit = min(max((int) ($request['section_limit'] ?? 10), 1), 50);
        $sectionId = ! empty($request['section_id']) ? (int) $request['section_id'] : null;

        $service = app(LeaderboardService::class);
        $seasons = $service->availableSeasons($admin, $workspaceId);

        $seasonId = (int) ($request['season_id'] ?? 0);
        $season = $seasonId > 0
            ? $seasons->firstWhere('id', $seasonId)
            : (Season::current() ?? $seasons->first());

        if (! $season) {
            $message = $seasonId > 0
                ? "Error: season #{$seasonId} not found or has no enrollments."
                : 'Error: no season found for this workspace.';

            $available = $seasons->map(fn (Season $item) => "{$item->name} (#{$item->id})")->values()->all();

            return $message.($available !== [] ? ' Available seasons: '.implode(', ', $available).'.' : '');
        }

        if ($sectionId) {
            $board = $service->forAdminSection($admin, $season, $sectionId);

            if ($board === []) {
                return "Error: section #{$sectionId} not found, has no enrollments in season \"{$season->name}\", or has no leaderboard data.";
            }

            return json_encode([
                'season' => ['id' => $season->id, 'name' => (string) $season->name],
                'section' => [
                    'id' => (int) $board['sectionId'],
                    'name' => (string) $board['sectionName'],
                    'workspace' => $board['workspaceName'] ?? null,
                    'total_players' => (int) $board['totalPlayers'],
                ],
                'leaders' => $this->mapUsers($board['users'] ?? [], $limit),
            ]);
        }

        $boards = $service->forAdminSections(
            $admin,
            $season,
            $workspaceId,
            maxVisibleUsers: $limit,
            sectionLimit: $sectionLimit,
        );

        $sections = collect($boards)->map(fn (array $board) => [
            'id' => (int) $board['sectionId'],
            'name' => (string) $board['sectionName'],
            'workspace' => $board['workspaceName'] ?? null,
            'total_players' => (int) $board['totalPlayers'],
            'leaders' => $this->mapUsers($board['users'] ?? [], $limit),
        ])->values();

        return json_encode([
            'season' => ['id' => $season->id, 'name' => (string) $season->name],
            'sections' => $sections,
            'note' => $sections->isEmpty()
                ? 'No sections with leaderboard enrollments found for this season.'
                : 'Only the top students per section are shown. Pass section_id for a fuller board.',
        ]);
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $users
     * @return array<int, array<string, mixed>>
     */
    private function mapUsers(iterable $users, int $limit): array
    {
        $mapped = [];
        $rank = 0;

        foreach ($users as $user) {
            $rank++;

            if ($rank > $limit) {
                break;
            }

            $mapped[] = [
                'rank' => $rank,
                'name' => $user['name'] ?? 'Unknown',
                'xp' => (float) ($user['xp'] ?? 0),
                'level' => (int) ($user['level'] ?? 1),
                'streak' => (int) ($user['streak'] ?? 0),
                'weekly_xp' => (int) ($user['weeklyXp'] ?? 0),
                'trend' => $user['trend'] ?? 'stable',
            ];
        }

        return $mapped;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section_id' => $schema->integer()->description('Optional section ID for a single full leaderboard. Omit for a per-section overview.'),
            'season_id' => $schema->integer()->description('Optional season ID (from the available seasons list). Defaults to the active season.'),
            'limit' => $schema->integer()->description('Maximum ranked students per section (1-50, default 10).'),
            'section_limit' => $schema->integer()->description('Maximum sections to include in the overview (1-50, default 10).'),
        ];
    }
}
