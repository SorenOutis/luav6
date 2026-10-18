<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Models\Workspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ListWorkspacesTool implements Tool
{
    public function name(): string
    {
        return 'list_workspaces';
    }

    public function description(): Stringable|string
    {
        return 'Super Administrator only. List platform workspaces (id, public_id, name, archived, student/admin counts). Use when the admin asks about all workspaces, another school, or platform-wide scope. Workspace admins must not receive this tool.';
    }

    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();
        if (! $user?->isSuperAdmin()) {
            return json_encode([
                'error' => 'platform_tool_required',
                'message' => 'list_workspaces is platform-wide and only available to Super Administrators.',
            ]);
        }

        $includeArchived = (bool) ($request['include_archived'] ?? false);
        $search = trim((string) ($request['search'] ?? ''));

        $query = Workspace::query()
            ->withCount([
                'users as student_count' => fn ($q) => $q->wherePivot('role', Workspace::ROLE_STUDENT),
                'users as admin_count' => fn ($q) => $q->wherePivotIn('role', [Workspace::ROLE_OWNER, Workspace::ROLE_ADMIN]),
                'sections',
                'exams',
            ])
            ->orderBy('name')
            ->limit(100);

        if (! $includeArchived) {
            $query->whereNull('archived_at');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        $rows = $query->get()->map(fn (Workspace $workspace): array => [
            'id' => $workspace->id,
            'public_id' => $workspace->public_id,
            'name' => $workspace->name,
            'slug' => $workspace->slug,
            'archived' => $workspace->isArchived(),
            'student_count' => (int) $workspace->student_count,
            'admin_count' => (int) $workspace->admin_count,
            'section_count' => (int) $workspace->sections_count,
            'exam_count' => (int) $workspace->exams_count,
        ]);

        return json_encode([
            'scope' => 'platform-wide',
            'count' => $rows->count(),
            'workspaces' => $rows,
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional workspace name or slug filter.'),
            'include_archived' => $schema->boolean()->description('Include archived workspaces. Defaults to false.'),
        ];
    }
}
