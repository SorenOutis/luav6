<?php

namespace App\Ai\Tools;

use App\Models\Badge;
use App\Models\Reward;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageBadgesRewardsTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'manage_badges_rewards';
    }

    public function description(): Stringable|string
    {
        return 'Prepare creating, updating, or deleting a gamification badge or shop reward (name, description, required level, points cost). This tool never writes directly; only the administrator approving in the UI can execute it.';
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

        $entity = trim((string) ($request['entity'] ?? ''));
        if (! in_array($entity, ['badge', 'reward'], true)) {
            return 'Error: entity must be "badge" or "reward".';
        }

        $operation = trim((string) ($request['operation'] ?? ''));
        if (! in_array($operation, ['create', 'update', 'delete'], true)) {
            return 'Error: operation must be one of: create, update, delete.';
        }

        $isBadge = $entity === 'badge';
        $class = $isBadge ? Badge::class : Reward::class;
        $label = $isBadge ? 'Badge' : 'Reward';

        if ($operation === 'create') {
            return $this->prepareCreate($request, $class, $label, $workspaceId);
        }

        return $this->prepareChange($request, $class, $label, $operation, $workspaceId);
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function prepareCreate(Request $request, string $class, string $label, int $workspaceId): Stringable|string
    {
        $name = trim((string) ($request['name'] ?? ''));
        if ($name === '') {
            return "Error: {$label} name is required.";
        }

        $attributes = $this->attributeChanges($request, $label, true);
        if (is_string($attributes)) {
            return $attributes;
        }

        $attributes['name'] = $name;

        $preview = [
            ['field' => "New {$label} name", 'before' => null, 'after' => $name],
        ];

        foreach ($attributes as $field => $value) {
            if ($field === 'name') {
                continue;
            }
            $preview[] = ['field' => ucfirst(str_replace('_', ' ', $field)), 'before' => null, 'after' => $value === null ? 'None' : (string) $value];
        }

        return $this->stageAction(
            'manage_badges_rewards',
            "Create {$label}",
            "Create {$label} \"{$name}\" in this workspace.",
            [
                'entity' => strtolower($label),
                'operation' => 'create',
                'attributes' => $attributes,
            ],
            $preview,
        );
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function prepareChange(Request $request, string $class, string $label, string $operation, int $workspaceId): Stringable|string
    {
        $recordId = (int) ($request['record_id'] ?? 0);
        $recordName = trim((string) ($request['record_name'] ?? ''));

        if ($recordId <= 0 && $recordName === '') {
            return "Error: provide record_id or record_name for the {$label}.";
        }

        $query = $class::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->when($recordId > 0, fn ($q) => $q->whereKey($recordId))
            ->when($recordId <= 0, fn ($q) => $q->where('name', 'like', "%{$recordName}%"));

        $matches = (clone $query)->limit(5)->get();

        if ($matches->isEmpty()) {
            return "Error: {$label} not found in this workspace.";
        }

        if ($recordId <= 0 && $matches->count() > 1) {
            $options = $matches->map(fn (Model $record) => "{$record->name} (#{$record->id})")->implode(', ');

            return "Error: multiple {$label}s match \"{$recordName}\": {$options}. Use record_id instead.";
        }

        /** @var Model $record */
        $record = $matches->first();

        if ($operation === 'delete') {
            return $this->stageAction(
                'manage_badges_rewards',
                "Delete {$label}",
                "Delete {$label} \"{$record->name}\" (#{$record->id}) from this workspace.",
                [
                    'entity' => strtolower($label),
                    'operation' => 'delete',
                    'record_id' => $record->id,
                    'expected_updated_at' => $record->updated_at?->toJSON(),
                ],
                [
                    ['field' => $label, 'before' => "{$record->name} (#{$record->id})", 'after' => 'Deleted'],
                ],
            );
        }

        $changes = $this->attributeChanges($request, $label, false);
        if (is_string($changes)) {
            return $changes;
        }

        $changes = array_filter(
            $changes,
            fn ($value, string $field) => $value !== null && (string) $record->getAttribute($field) !== (string) $value,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changes === []) {
            return "No changes were specified for {$label} \"{$record->name}\" (#{$record->id}).";
        }

        $preview = [
            ['field' => $label, 'before' => "{$record->name} (#{$record->id})", 'after' => null],
        ];

        foreach ($changes as $field => $value) {
            $preview[] = [
                'field' => ucfirst(str_replace('_', ' ', $field)),
                'before' => (string) ($record->getAttribute($field) ?? 'None'),
                'after' => (string) $value,
            ];
        }

        return $this->stageAction(
            'manage_badges_rewards',
            "Update {$label}",
            "Update {$label} \"{$record->name}\" (#{$record->id}).",
            [
                'entity' => strtolower($label),
                'operation' => 'update',
                'record_id' => $record->id,
                'changes' => $changes,
                'expected_updated_at' => $record->updated_at?->toJSON(),
            ],
            $preview,
        );
    }

    /**
     * @return array<string, mixed>|string
     */
    private function attributeChanges(Request $request, string $label, bool $creating): array|string
    {
        $isBadge = $label === 'Badge';
        $changes = [];

        if (array_key_exists('name', $request->all()) && ! $creating) {
            $name = trim((string) $request['name']);
            if ($name !== '') {
                $changes['name'] = $name;
            }
        }

        if (array_key_exists('description', $request->all())) {
            $changes['description'] = trim((string) $request['description']) ?: null;
        }

        if ($isBadge && array_key_exists('required_level', $request->all())) {
            $level = (int) $request['required_level'];
            if ($level < 1) {
                return 'Error: required_level must be at least 1.';
            }
            $changes['required_level'] = $level;
        }

        if ($isBadge && array_key_exists('icon_url', $request->all())) {
            $url = trim((string) $request['icon_url']);
            $changes['icon_url'] = $url !== '' ? $url : null;
        }

        if (! $isBadge && array_key_exists('points_cost', $request->all())) {
            $cost = (int) $request['points_cost'];
            if ($cost < 0) {
                return 'Error: points_cost cannot be negative.';
            }
            $changes['points_cost'] = $cost;
        }

        if ($creating && $isBadge && ! array_key_exists('required_level', $request->all())) {
            $changes['required_level'] = 1;
        }

        if ($creating && ! $isBadge && ! array_key_exists('points_cost', $request->all())) {
            $changes['points_cost'] = 0;
        }

        return $changes;
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('"badge" or "reward".')->required(),
            'operation' => $schema->string()->description('create, update, or delete.')->required(),
            'record_id' => $schema->integer()->description('Badge/reward ID for update or delete.'),
            'record_name' => $schema->string()->description('Badge/reward name for update or delete, alternative to record_id.'),
            'name' => $schema->string()->description('Name (required for create; optional rename for update).'),
            'description' => $schema->string()->description('Description of what the badge/reward is for.'),
            'required_level' => $schema->integer()->description('Badges only: the level at which this badge is automatically awarded.'),
            'icon_url' => $schema->string()->description('Badges only: optional icon URL.'),
            'points_cost' => $schema->integer()->description('Rewards only: cost in gamification points.'),
        ];
    }
}
