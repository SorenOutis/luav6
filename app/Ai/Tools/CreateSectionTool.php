<?php

namespace App\Ai\Tools;

use App\Models\Season;
use App\Models\Section;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateSectionTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'create_section';
    }

    public function description(): Stringable|string
    {
        return 'Prepare creation of a new class section in the active workspace for human review. This tool never creates the section directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $name = trim((string) ($request['name'] ?? ''));
        if ($name === '') {
            return 'Error: section name is required (e.g. "Grade 10 - Rizal" or "BSIT 3-A").';
        }

        $schoolLevel = trim((string) ($request['school_level'] ?? Section::SCHOOL_LEVEL_COLLEGE));
        if (! in_array($schoolLevel, [Section::SCHOOL_LEVEL_COLLEGE, Section::SCHOOL_LEVEL_SENIOR_HIGH], true)) {
            $schoolLevel = Section::SCHOOL_LEVEL_COLLEGE;
        }

        $leaderboardEnabled = (bool) ($request['leaderboard_enabled'] ?? true);

        $season = Season::current();
        $seasonLabel = $season ? $season->name : 'Active School Year';

        $payload = [
            'name' => $name,
            'school_level' => $schoolLevel,
            'leaderboard_enabled' => $leaderboardEnabled,
            'season_id' => $season?->id,
        ];

        $levelLabel = $schoolLevel === Section::SCHOOL_LEVEL_SENIOR_HIGH ? 'Senior High School' : 'College';

        $preview = [
            ['field' => 'Section Name', 'before' => null, 'after' => $name],
            ['field' => 'School Level', 'before' => null, 'after' => $levelLabel],
            ['field' => 'School Year / Season', 'before' => null, 'after' => $seasonLabel],
            ['field' => 'Leaderboard', 'before' => null, 'after' => $leaderboardEnabled ? 'Enabled' : 'Disabled'],
            ['field' => 'Join Code', 'before' => null, 'after' => 'Auto-generated on creation'],
            ['field' => 'Workspace', 'before' => null, 'after' => $this->workspaceName()],
        ];

        return $this->stageAction(
            'create_section',
            'Create class section',
            "Create class section \"{$name}\" ({$levelLabel}) in {$this->workspaceName()}.",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The section or class name, e.g. "Grade 10 - Rizal" or "CS 101 - A".')->required(),
            'school_level' => $schema->string()->description('Either "college" or "senior_high". Defaults to "college".'),
            'leaderboard_enabled' => $schema->boolean()->description('Whether gamification leaderboard is enabled. Defaults to true.'),
        ];
    }
}
