<?php

namespace App\Ai\Tools;

use App\Models\Season;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SeasonAdminTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'season_admin';
    }

    public function description(): Stringable|string
    {
        return 'Prepare creating, updating, activating, or deactivating a gamification season (name, dates, countdown flag). Activating a season automatically deactivates the other seasons in this workspace. This tool never writes directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $workspaceId = $this->workspaceId();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $mode = trim((string) ($request['mode'] ?? 'create'));
        if (! in_array($mode, ['create', 'update', 'activate', 'deactivate'], true)) {
            return 'Error: mode must be one of: create, update, activate, deactivate.';
        }

        if ($mode === 'create') {
            return $this->prepareCreate($request, $workspaceId);
        }

        return $this->prepareChange($request, $workspaceId, $mode);
    }

    private function prepareCreate(Request $request, int $workspaceId): Stringable|string
    {
        $name = trim((string) ($request['name'] ?? ''));
        if ($name === '') {
            return 'Error: season name is required.';
        }

        $startRaw = trim((string) ($request['start_date'] ?? ''));
        if ($startRaw === '') {
            return 'Error: start_date is required (e.g. "2026-01-05").';
        }

        try {
            $start = Carbon::parse($startRaw);
        } catch (\Throwable) {
            return 'Error: invalid start_date format. Use YYYY-MM-DD.';
        }

        $end = null;
        $endRaw = trim((string) ($request['end_date'] ?? ''));
        if ($endRaw !== '') {
            try {
                $end = Carbon::parse($endRaw);
            } catch (\Throwable) {
                return 'Error: invalid end_date format. Use YYYY-MM-DD.';
            }

            if ($end->lt($start)) {
                return 'Error: end_date must be on or after start_date.';
            }
        }

        $isActive = filter_var($request['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $countdown = filter_var($request['show_countdown_on_welcome'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($isActive) {
            $current = Season::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $workspaceId)
                ->where('is_active', true)
                ->value('name');

            if ($current) {
                return "Error: \"{$current}\" is already the active season. Deactivate it first or use mode \"update\" on it.";
            }
        }

        $preview = [
            ['field' => 'Season', 'before' => null, 'after' => $name],
            ['field' => 'Start date', 'before' => null, 'after' => $start->toDateString()],
            ['field' => 'End date', 'before' => null, 'after' => $end?->toDateString() ?? 'None'],
            ['field' => 'Active', 'before' => null, 'after' => $isActive ? 'Yes' : 'No'],
            ['field' => 'Welcome countdown', 'before' => null, 'after' => $countdown ? 'Enabled' : 'Disabled'],
        ];

        return $this->stageAction(
            'season_admin',
            'Create season',
            "Create season \"{$name}\" starting {$start->toDateString()}".($isActive ? ' and make it the active season.' : '.'),
            [
                'mode' => 'create',
                'attributes' => [
                    'name' => $name,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end?->toDateString(),
                    'is_active' => $isActive,
                    'show_countdown_on_welcome' => $countdown,
                ],
            ],
            $preview,
        );
    }

    private function prepareChange(Request $request, int $workspaceId, string $mode): Stringable|string
    {
        $seasonId = (int) ($request['season_id'] ?? 0);
        $seasonName = trim((string) ($request['season_name'] ?? ''));

        if ($seasonId <= 0 && $seasonName === '') {
            return 'Error: provide season_id or season_name.';
        }

        $season = Season::query()
            ->withoutGlobalScope('workspace')
            ->where('seasons.workspace_id', $workspaceId)
            ->when($seasonId > 0, fn ($query) => $query->whereKey($seasonId))
            ->when($seasonId <= 0, fn ($query) => $query->where('name', 'like', "%{$seasonName}%"))
            ->orderByDesc('start_date')
            ->first();

        if (! $season) {
            return 'Error: season not found in this workspace.';
        }

        $changes = [];
        $preview = [
            ['field' => 'Season', 'before' => "{$season->name} (#{$season->id})", 'after' => null],
        ];

        if ($mode === 'activate' || $mode === 'deactivate') {
            $wantActive = $mode === 'activate';

            if ((bool) $season->is_active === $wantActive) {
                return "Season \"{$season->name}\" (#{$season->id}) is already ".($wantActive ? 'active' : 'inactive').'.';
            }

            $changes['is_active'] = $wantActive;

            if ($wantActive) {
                $current = Season::query()
                    ->withoutGlobalScope('workspace')
                    ->where('workspace_id', $workspaceId)
                    ->where('is_active', true)
                    ->whereKeyNot($season->id)
                    ->value('name');

                if ($current) {
                    $preview[] = ['field' => 'Currently active season', 'before' => $current, 'after' => 'Will be deactivated automatically'];
                }
            }
        } else {
            $name = trim((string) ($request['name'] ?? ''));
            if ($name !== '' && $name !== $season->name) {
                $changes['name'] = $name;
                $preview[] = ['field' => 'Name', 'before' => $season->name, 'after' => $name];
            }

            $startRaw = trim((string) ($request['start_date'] ?? ''));
            if ($startRaw !== '') {
                try {
                    $start = Carbon::parse($startRaw);
                } catch (\Throwable) {
                    return 'Error: invalid start_date format. Use YYYY-MM-DD.';
                }
                if ($season->start_date?->toDateString() !== $start->toDateString()) {
                    $changes['start_date'] = $start->toDateString();
                    $preview[] = ['field' => 'Start date', 'before' => $season->start_date?->toDateString(), 'after' => $start->toDateString()];
                }
            }

            if (array_key_exists('end_date', $request->all())) {
                $endRaw = trim((string) ($request['end_date'] ?? ''));
                $end = null;
                if ($endRaw !== '') {
                    try {
                        $end = Carbon::parse($endRaw);
                    } catch (\Throwable) {
                        return 'Error: invalid end_date format. Use YYYY-MM-DD.';
                    }
                }

                if ($season->end_date?->toDateString() !== $end?->toDateString()) {
                    $changes['end_date'] = $end?->toDateString();
                    $preview[] = ['field' => 'End date', 'before' => $season->end_date?->toDateString() ?? 'None', 'after' => $end?->toDateString() ?? 'None'];
                    $preview[] = ['field' => 'Note', 'before' => null, 'after' => 'End date order is validated on approval.'];
                }
            }

            if (array_key_exists('is_active', $request->all())) {
                $wantActive = filter_var($request['is_active'], FILTER_VALIDATE_BOOLEAN);
                if ((bool) $season->is_active !== $wantActive) {
                    $changes['is_active'] = $wantActive;
                    $preview[] = ['field' => 'Active', 'before' => $season->is_active ? 'Yes' : 'No', 'after' => $wantActive ? 'Yes (other seasons will be deactivated)' : 'No'];
                }
            }

            if (array_key_exists('show_countdown_on_welcome', $request->all())) {
                $wantCountdown = filter_var($request['show_countdown_on_welcome'], FILTER_VALIDATE_BOOLEAN);
                if ((bool) $season->show_countdown_on_welcome !== $wantCountdown) {
                    $changes['show_countdown_on_welcome'] = $wantCountdown;
                    $preview[] = ['field' => 'Welcome countdown', 'before' => $season->show_countdown_on_welcome ? 'Enabled' : 'Disabled', 'after' => $wantCountdown ? 'Enabled' : 'Disabled'];
                }
            }

            if ($changes === []) {
                return "No changes were specified for season \"{$season->name}\" (#{$season->id}).";
            }
        }

        return $this->stageAction(
            'season_admin',
            ucfirst($mode).' season',
            "{$mode} season \"{$season->name}\" (#{$season->id}).",
            [
                'mode' => 'update',
                'season_id' => $season->id,
                'changes' => $changes,
                'expected_updated_at' => $season->updated_at?->toJSON(),
            ],
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'mode' => $schema->string()->description('create (default), update, activate, or deactivate.'),
            'season_id' => $schema->integer()->description('Season ID for update/activate/deactivate.'),
            'season_name' => $schema->string()->description('Season name (or partial name) for update/activate/deactivate, alternative to season_id.'),
            'name' => $schema->string()->description('Season name (required for create; optional rename for update).'),
            'start_date' => $schema->string()->description('Start date, e.g. "2026-01-05" (required for create).'),
            'end_date' => $schema->string()->description('Optional end date, e.g. "2026-04-05". Pass an empty string to clear it on update.'),
            'is_active' => $schema->boolean()->description('Whether this season becomes the active season (create/update). At most one workspace season can be active.'),
            'show_countdown_on_welcome' => $schema->boolean()->description('Whether the welcome page shows a season countdown.'),
        ];
    }
}
