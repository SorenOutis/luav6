<?php

namespace App\Ai\Tools;

use App\Models\ActivityTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteActivityTaskTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'delete_activity_task';
    }

    public function description(): Stringable|string
    {
        return 'Prepare deletion of an activity task from the active workspace for human review. This tool never deletes the task directly; only the administrator approving in the UI can execute it.';
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
            ->with(['section:id,name', 'scores'])
            ->first();

        if (! $task) {
            return "Error: activity task with ID {$taskId} not found in this workspace. Check activity_tasks_admin for valid IDs.";
        }

        $payload = [
            'task_id' => $task->id,
            'expected_updated_at' => $task->updated_at?->toJSON(),
        ];

        $preview = [
            ['field' => 'Task ID', 'before' => (string) $task->id, 'after' => 'Deleted'],
            ['field' => 'Title', 'before' => $task->title, 'after' => 'Deleted'],
            ['field' => 'Section', 'before' => $task->section?->name ?? 'None', 'after' => 'Deleted'],
            ['field' => 'Recorded Scores', 'before' => (string) $task->scores->count(), 'after' => 'Removed'],
        ];

        return $this->stageAction(
            'delete_activity_task',
            'Delete activity task',
            "Delete activity task \"{$task->title}\" (ID {$task->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'task_id' => $schema->integer()->description('The ID of the activity task to delete.')->required(),
        ];
    }
}
