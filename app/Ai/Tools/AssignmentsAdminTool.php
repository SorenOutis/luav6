<?php

namespace App\Ai\Tools;

use App\Models\Assignment;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AssignmentsAdminTool implements Tool
{
    public function name(): string
    {
        return 'assignments_admin';
    }

    public function description(): Stringable|string
    {
        return 'List all assignments in the active workspace with ID, title, due date, status, assigned sections, and submission counts.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        $assignments = Assignment::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->with(['sections:id,name', 'course:id,name'])
            ->withCount('submissions')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (Assignment $assignment) => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'due_date' => $assignment->due_date?->format('M d, Y g:i A'),
                'status' => $assignment->status,
                'course' => $assignment->course?->name,
                'sections' => $assignment->sections->pluck('name')->values()->all(),
                'submissions_count' => $assignment->submissions_count,
            ])
            ->values();

        if ($assignments->isEmpty()) {
            return 'There are no assignments in this workspace yet. You can create one using create_assignment.';
        }

        return (string) json_encode($assignments);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
