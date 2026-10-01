<?php

namespace App\Ai\Tools;

use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateActivityTaskTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'create_activity_task';
    }

    public function description(): Stringable|string
    {
        return 'Prepare creation of a class activity task (e.g. recitation, lab activity, project) for human review. This tool never creates the task directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $sectionId = (int) ($request['section_id'] ?? 0);
        $section = Section::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($sectionId)
            ->where('workspace_id', $this->workspaceId())
            ->first();

        if (! $section) {
            return "Error: section with ID {$sectionId} not found in this workspace. Check workspace_overview or sections_admin for valid section IDs.";
        }

        $title = trim((string) ($request['title'] ?? ''));
        if ($title === '') {
            return 'Error: activity task title is required.';
        }

        $maxPoints = isset($request['max_points']) && is_numeric($request['max_points'])
            ? (float) $request['max_points']
            : 100.0;

        $term = trim((string) ($request['term'] ?? 'Midterm')) ?: 'Midterm';
        $description = trim((string) ($request['description'] ?? '')) ?: null;

        $dueDate = null;
        if (! empty($request['due_date'])) {
            try {
                $dueDate = Carbon::parse($request['due_date']);
            } catch (\Throwable) {
                return 'Error: invalid due_date format.';
            }
        }

        $payload = [
            'section_id' => $section->id,
            'title' => $title,
            'term' => $term,
            'max_points' => $maxPoints,
            'description' => $description,
            'due_date' => $dueDate?->toDateString(),
        ];

        $preview = [
            ['field' => 'Activity Title', 'before' => null, 'after' => $title],
            ['field' => 'Section', 'before' => null, 'after' => "{$section->name} (#{$section->id})"],
            ['field' => 'Term', 'before' => null, 'after' => $term],
            ['field' => 'Max Points', 'before' => null, 'after' => (string) $maxPoints],
            ['field' => 'Due Date', 'before' => null, 'after' => $dueDate?->format('M d, Y') ?? 'None'],
        ];

        return $this->stageAction(
            'create_activity_task',
            'Create activity task',
            "Create task \"{$title}\" for section {$section->name}.",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'section_id' => $schema->integer()->description('The ID of the section to assign this activity to.')->required(),
            'title' => $schema->string()->description('The task or activity title, e.g. "Laboratory Exercise 1".')->required(),
            'max_points' => $schema->number()->description('Max possible points (defaults to 100).'),
            'term' => $schema->string()->description('Grading term (e.g. "Prelim", "Midterm", "Final").'),
            'due_date' => $schema->string()->description('Optional due date, e.g. "2026-10-20".'),
            'description' => $schema->string()->description('Optional activity instructions.'),
        ];
    }
}
