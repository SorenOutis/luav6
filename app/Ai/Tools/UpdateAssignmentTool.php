<?php

namespace App\Ai\Tools;

use App\Models\Assignment;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateAssignmentTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'update_assignment';
    }

    public function description(): Stringable|string
    {
        return 'Prepare updates to an existing assignment in the active workspace for human review. This tool never updates the assignment directly; only the administrator approving in the UI can execute it.';
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
            ->with('sections')
            ->first();

        if (! $assignment) {
            return "Error: assignment with ID {$assignmentId} not found in this workspace. Check assignments_admin for valid assignment IDs.";
        }

        $changes = [];
        $preview = [];

        if (isset($request['title']) && trim((string) $request['title']) !== '') {
            $newTitle = trim((string) $request['title']);
            if ($newTitle !== $assignment->title) {
                $changes['title'] = $newTitle;
                $preview[] = ['field' => 'Title', 'before' => $assignment->title, 'after' => $newTitle];
            }
        }

        if (isset($request['description'])) {
            $newDesc = trim((string) $request['description']) ?: null;
            if ($newDesc !== $assignment->description) {
                $changes['description'] = $newDesc;
                $preview[] = ['field' => 'Description', 'before' => $assignment->description ?? 'None', 'after' => $newDesc ?? 'None'];
            }
        }

        if (isset($request['due_date']) && trim((string) $request['due_date']) !== '') {
            try {
                $newDue = Carbon::parse($request['due_date']);
                $changes['due_date'] = $newDue->toIso8601String();
                $preview[] = [
                    'field' => 'Due Date',
                    'before' => $assignment->due_date?->format('M d, Y g:i A') ?? 'None',
                    'after' => $newDue->format('M d, Y g:i A'),
                ];
            } catch (\Throwable) {
                return 'Error: invalid due_date format.';
            }
        }

        if (isset($request['section_ids'])) {
            $raw = is_array($request['section_ids'])
                ? $request['section_ids']
                : explode(',', (string) $request['section_ids']);

            $newSectionIds = collect($raw)
                ->map(fn ($id) => (int) trim((string) $id))
                ->filter(fn (int $id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            $currentSectionIds = $assignment->sections->pluck('id')->sort()->values()->all();
            $sortedNew = collect($newSectionIds)->sort()->values()->all();

            if ($currentSectionIds !== $sortedNew) {
                $sections = Section::query()
                    ->withoutGlobalScope('workspace')
                    ->whereIn('id', $newSectionIds)
                    ->where('workspace_id', $this->workspaceId())
                    ->get();

                if ($sections->count() !== count($newSectionIds)) {
                    return 'Error: one or more specified sections do not exist in this workspace.';
                }

                $beforeNames = $assignment->sections->pluck('name')->implode(', ') ?: 'None';
                $afterNames = $sections->pluck('name')->implode(', ') ?: 'None';

                $changes['section_ids'] = $newSectionIds;
                $preview[] = ['field' => 'Sections', 'before' => $beforeNames, 'after' => $afterNames];
            }
        }

        if ($changes === []) {
            return "No changes were specified for assignment \"{$assignment->title}\" (#{$assignment->id}).";
        }

        $payload = [
            'assignment_id' => $assignment->id,
            'changes' => $changes,
            'expected_updated_at' => $assignment->updated_at?->toJSON(),
        ];

        return $this->stageAction(
            'update_assignment',
            'Update assignment',
            "Update assignment \"{$assignment->title}\" (#{$assignment->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'assignment_id' => $schema->integer()->description('The ID of the assignment to update.')->required(),
            'title' => $schema->string()->description('Optional updated assignment title.'),
            'description' => $schema->string()->description('Optional updated assignment description.'),
            'due_date' => $schema->string()->description('Optional updated due date, e.g. "2026-10-15 23:59".'),
            'section_ids' => $schema->string()->description('Optional comma-separated list of target section IDs.'),
        ];
    }
}
