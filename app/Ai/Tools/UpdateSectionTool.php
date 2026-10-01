<?php

namespace App\Ai\Tools;

use App\Models\Section;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateSectionTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'update_section';
    }

    public function description(): Stringable|string
    {
        return 'Prepare updates to an existing class section in the active workspace for human review. This tool never updates the section directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $sectionId = (int) ($request['section_id'] ?? 0);
        $section = Section::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($sectionId)
            ->where('workspace_id', $this->workspaceId())
            ->first();

        if (! $section) {
            return "Error: section with ID {$sectionId} not found in this workspace. Check workspace_overview or sections_admin for valid section IDs.";
        }

        $changes = [];
        $preview = [];

        if (isset($request['name']) && trim((string) $request['name']) !== '') {
            $newName = trim((string) $request['name']);
            if ($newName !== $section->name) {
                $changes['name'] = $newName;
                $preview[] = ['field' => 'Name', 'before' => $section->name, 'after' => $newName];
            }
        }

        if (isset($request['school_level'])) {
            $newLevel = trim((string) $request['school_level']);
            if (in_array($newLevel, [Section::SCHOOL_LEVEL_COLLEGE, Section::SCHOOL_LEVEL_SENIOR_HIGH], true) && $newLevel !== $section->school_level) {
                $changes['school_level'] = $newLevel;
                $preview[] = ['field' => 'School Level', 'before' => $section->school_level, 'after' => $newLevel];
            }
        }

        if (isset($request['leaderboard_enabled'])) {
            $newLb = (bool) $request['leaderboard_enabled'];
            if ($newLb !== (bool) $section->leaderboard_enabled) {
                $changes['leaderboard_enabled'] = $newLb;
                $preview[] = ['field' => 'Leaderboard', 'before' => $section->leaderboard_enabled ? 'Enabled' : 'Disabled', 'after' => $newLb ? 'Enabled' : 'Disabled'];
            }
        }

        if ($changes === []) {
            return "No changes were specified for section \"{$section->name}\" (#{$section->id}).";
        }

        $payload = [
            'section_id' => $section->id,
            'changes' => $changes,
            'expected_updated_at' => $section->updated_at?->toJSON(),
        ];

        return $this->stageAction(
            'update_section',
            'Update class section',
            "Update section \"{$section->name}\" (#{$section->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section_id' => $schema->integer()->description('The ID of the section to update.')->required(),
            'name' => $schema->string()->description('Optional updated section name.'),
            'school_level' => $schema->string()->description('Optional school level: "college" or "senior_high".'),
            'leaderboard_enabled' => $schema->boolean()->description('Optional boolean flag for leaderboard.'),
        ];
    }
}
