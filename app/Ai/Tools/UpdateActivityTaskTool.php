<?php

namespace App\Ai\Tools;

use App\Models\ActivityTask;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateActivityTaskTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'update_activity_task';
    }

    public function description(): Stringable|string
    {
        return 'Prepare updates to an existing class activity task (title, assessment type, term, max points, due date, description) for human review. This tool never updates the task directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $taskId = (int) ($request['task_id'] ?? 0);
        $task = ActivityTask::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($taskId)
            ->where('workspace_id', $this->workspaceId())
            ->first();

        if (! $task) {
            return "Error: activity task with ID {$taskId} not found in this workspace. Check activity_tasks_admin for valid task IDs.";
        }

        $changes = [];
        $preview = [];

        if (isset($request['title']) && trim((string) $request['title']) !== '') {
            $newTitle = trim((string) $request['title']);
            if ($newTitle !== $task->title) {
                $changes['title'] = $newTitle;
                $preview[] = ['field' => 'Activity Title', 'before' => $task->title, 'after' => $newTitle];
            }
        }

        if (isset($request['task_type']) && trim((string) $request['task_type']) !== '') {
            $newType = trim((string) $request['task_type']);
            if ($newType !== $task->task_type) {
                $changes['task_type'] = $newType;
                $preview[] = ['field' => 'Assessment Type', 'before' => $task->task_type, 'after' => $newType];
            }
        }

        if (isset($request['term']) && trim((string) $request['term']) !== '') {
            $newTerm = trim((string) $request['term']);
            if ($newTerm !== $task->term) {
                $changes['term'] = $newTerm;
                $preview[] = ['field' => 'Term', 'before' => $task->term, 'after' => $newTerm];
            }
        }

        if (isset($request['max_points']) && is_numeric($request['max_points'])) {
            $newMax = (float) $request['max_points'];
            if ($newMax <= 0) {
                return 'Error: max_points must be greater than 0.';
            }
            if ($newMax !== (float) $task->max_points) {
                $changes['max_points'] = $newMax;
                $preview[] = ['field' => 'Max Points', 'before' => (string) $task->max_points, 'after' => (string) $newMax];
            }
        }

        if (isset($request['due_date'])) {
            $rawDue = trim((string) $request['due_date']);
            $newDue = null;
            if ($rawDue !== '') {
                try {
                    $newDue = Carbon::parse($rawDue);
                } catch (\Throwable) {
                    return 'Error: invalid due_date format.';
                }
            }

            if ($newDue?->toDateString() !== $task->due_date?->toDateString()) {
                $changes['due_date'] = $newDue?->toDateString();
                $preview[] = [
                    'field' => 'Due Date',
                    'before' => $task->due_date?->format('M d, Y') ?? 'None',
                    'after' => $newDue?->format('M d, Y') ?? 'None',
                ];
            }
        }

        if (isset($request['description'])) {
            $newDesc = trim((string) $request['description']) ?: null;
            if ($newDesc !== $task->description) {
                $changes['description'] = $newDesc;
                $preview[] = ['field' => 'Description', 'before' => $task->description ?? 'None', 'after' => $newDesc ?? 'None'];
            }
        }

        if ($changes === []) {
            return "No changes were specified for activity task \"{$task->title}\" (#{$task->id}).";
        }

        $payload = [
            'task_id' => $task->id,
            'changes' => $changes,
            'expected_updated_at' => $task->updated_at?->toJSON(),
        ];

        return $this->stageAction(
            'update_activity_task',
            'Update activity task',
            "Update activity task \"{$task->title}\" (#{$task->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('The ID of the activity task to update.')->required(),
            'title' => $schema->string()->description('Optional updated task or activity title.'),
            'task_type' => $schema->string()->description('Optional updated assessment type, e.g. "Performance Task", "Laboratory", "Project", "Recitation".'),
            'term' => $schema->string()->description('Optional updated grading period or term, e.g. "First Semester - 1st Quarter" for SHS; "Prelim", "Midterm", "Final" for College.'),
            'max_points' => $schema->number()->description('Optional updated max points (must be greater than 0).'),
            'due_date' => $schema->string()->description('Optional updated due date, e.g. "2026-10-20".'),
            'description' => $schema->string()->description('Optional updated activity instructions or rubric notes.'),
        ];
    }
}
