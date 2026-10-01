<?php

namespace App\Ai\Tools;

use App\Models\Assignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteAssignmentTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'delete_assignment';
    }

    public function description(): Stringable|string
    {
        return 'Prepare deletion of an assignment from the active workspace for human review. This tool never deletes the assignment directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $assignmentId = (int) ($request['assignment_id'] ?? 0);
        $assignment = Assignment::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($assignmentId)
            ->where('workspace_id', $this->workspaceId())
            ->with(['sections', 'submissions'])
            ->first();

        if (! $assignment) {
            return "Error: assignment with ID {$assignmentId} not found in this workspace.";
        }

        $sectionNames = $assignment->sections->pluck('name')->implode(', ') ?: 'None';

        $payload = [
            'assignment_id' => $assignment->id,
            'expected_updated_at' => $assignment->updated_at?->toJSON(),
        ];

        $preview = [
            ['field' => 'Assignment ID', 'before' => (string) $assignment->id, 'after' => 'Deleted'],
            ['field' => 'Title', 'before' => $assignment->title, 'after' => 'Deleted'],
            ['field' => 'Due Date', 'before' => $assignment->due_date?->format('M d, Y g:i A') ?? 'None', 'after' => 'Deleted'],
            ['field' => 'Sections', 'before' => $sectionNames, 'after' => 'Unassigned'],
            ['field' => 'Submissions', 'before' => (string) $assignment->submissions->count(), 'after' => 'Removed'],
        ];

        return $this->stageAction(
            'delete_assignment',
            'Delete assignment',
            "Delete assignment \"{$assignment->title}\" (ID {$assignment->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'assignment_id' => $schema->integer()->description('The ID of the assignment to delete.')->required(),
        ];
    }
}
