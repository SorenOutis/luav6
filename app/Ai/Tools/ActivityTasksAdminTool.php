<?php

namespace App\Ai\Tools;

use App\Models\ActivityTask;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ActivityTasksAdminTool implements Tool
{
    public function name(): string
    {
        return 'activity_tasks_admin';
    }

    public function description(): Stringable|string
    {
        return 'List activity tasks in the active workspace with task ID, title, class section, term, max points, and due date.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        $tasks = ActivityTask::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->with('section:id,name')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (ActivityTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'section' => $task->section?->name,
                'term' => $task->term,
                'task_type' => $task->task_type,
                'max_points' => (float) $task->max_points,
                'due_date' => $task->due_date?->format('M d, Y'),
            ])
            ->values();

        if ($tasks->isEmpty()) {
            return 'There are no activity tasks in this workspace yet. You can create one using create_activity_task.';
        }

        return (string) json_encode($tasks);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
