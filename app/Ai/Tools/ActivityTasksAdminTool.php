<?php

namespace App\Ai\Tools;

use App\Models\ActivityTask;
use App\Models\Section;
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

        $query = ActivityTask::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->with('section:id,name');

        $search = trim((string) ($request['search'] ?? ''));
        if ($search !== '') {
            $query->where('title', 'like', "%{$search}%");
        }

        $sectionId = (int) ($request['section_id'] ?? 0);
        if ($sectionId > 0) {
            $query->where('section_id', $sectionId);
        } elseif (! empty($request['section_name'])) {
            $sectionName = trim((string) $request['section_name']);
            $section = Section::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $workspaceId)
                ->where(function ($q) use ($sectionName) {
                    $q->where('name', $sectionName)->orWhere('name', 'like', "%{$sectionName}%");
                })
                ->first();
            if ($section) {
                $query->where('section_id', $section->id);
            }
        }

        $tasks = $query->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (ActivityTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'section' => $task->section?->name,
                'section_id' => $task->section_id,
                'term' => $task->term,
                'task_type' => $task->task_type,
                'max_points' => (float) $task->max_points,
                'due_date' => $task->due_date?->format('M d, Y'),
            ])
            ->values();

        if ($tasks->isEmpty()) {
            return 'There are no activity tasks matching in this workspace yet. You can create one using create_activity_task.';
        }

        return (string) json_encode($tasks);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional search term to filter activity tasks by title (e.g. "Additional Points", "Quiz").'),
            'section_id' => $schema->integer()->description('Optional class section ID to filter activity tasks for a specific section.'),
            'section_name' => $schema->string()->description('Optional class section name (e.g. "BSIT 1-A", "Grade 11 - STEM Einstein").'),
        ];
    }
}
