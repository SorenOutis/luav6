<?php

namespace App\Ai\Tools;

use App\Models\Section;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SectionsAdminTool implements Tool
{
    public function name(): string
    {
        return 'sections_admin';
    }

    public function description(): Stringable|string
    {
        return 'List all class sections in the active workspace with section ID, name, join code, school level, enrolled student count, and exam count.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        $sections = Section::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->withCount(['users', 'exams'])
            ->orderBy('name')
            ->get()
            ->map(fn (Section $section) => [
                'id' => $section->id,
                'name' => $section->name,
                'join_code' => $section->join_code,
                'school_level' => $section->school_level,
                'leaderboard_enabled' => (bool) $section->leaderboard_enabled,
                'enrolled_students' => $section->users_count,
                'exams_count' => $section->exams_count,
            ])
            ->values();

        if ($sections->isEmpty()) {
            return 'There are no class sections in this workspace yet. You can create one using create_section.';
        }

        return (string) json_encode($sections);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
