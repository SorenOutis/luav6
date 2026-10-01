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
        $section = null;
        if ($sectionId > 0) {
            $section = Section::query()
                ->withoutGlobalScope('workspace')
                ->whereKey($sectionId)
                ->where('workspace_id', $this->workspaceId())
                ->first();
        }

        if (! $section && ! empty($request['section_name'])) {
            $section = Section::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $this->workspaceId())
                ->where('name', trim((string) $request['section_name']))
                ->first();
        }

        if (! $section) {
            $section = Section::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $this->workspaceId())
                ->orderBy('id')
                ->first();
        }

        if (! $section) {
            return 'Error: no sections exist in this workspace. Create a class section first.';
        }

        $title = trim((string) ($request['title'] ?? ''));
        if ($title === '') {
            return 'Error: activity task title is required.';
        }

        $maxPoints = isset($request['max_points']) && is_numeric($request['max_points'])
            ? (float) $request['max_points']
            : 100.0;

        $term = trim((string) ($request['term'] ?? ''));
        if ($term === '') {
            $titleLower = strtolower($title);
            if (str_contains($titleLower, 'prelim')) {
                $term = 'Prelim';
            } elseif (str_contains($titleLower, 'midterm')) {
                $term = 'Midterm';
            } elseif (str_contains($titleLower, 'semi-final') || str_contains($titleLower, 'semifinal')) {
                $term = 'Semi-Final';
            } elseif (str_contains($titleLower, 'final')) {
                $term = 'Final';
            } elseif (str_contains($titleLower, '1st quarter') || str_contains($titleLower, 'quarter 1')) {
                $term = 'First Semester - 1st Quarter';
            } elseif (str_contains($titleLower, '2nd quarter') || str_contains($titleLower, 'quarter 2')) {
                $term = 'First Semester - 2nd Quarter';
            } elseif (str_contains($titleLower, '3rd quarter') || str_contains($titleLower, 'quarter 3')) {
                $term = 'Second Semester - 1st Quarter';
            } elseif (str_contains($titleLower, '4th quarter') || str_contains($titleLower, 'quarter 4')) {
                $term = 'Second Semester - 2nd Quarter';
            } elseif ($section->school_level === Section::SCHOOL_LEVEL_SENIOR_HIGH) {
                $term = 'First Semester - 1st Quarter';
            } else {
                $term = 'Midterm';
            }
        }

        $description = trim((string) ($request['description'] ?? '')) ?: null;

        $rawDueDate = (string) ($request['due_date'] ?? '');
        if ($rawDueDate === '') {
            $dueDate = now()->addDays(7);
        } else {
            try {
                $dueDate = Carbon::parse($rawDueDate);
            } catch (\Throwable) {
                $dueDate = now()->addDays(7);
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
            'title' => $schema->string()->description('The task or activity title, e.g. "Laboratory Exercise 1".')->required(),
            'section_id' => $schema->integer()->description('Optional ID of the section to assign this activity to. Defaults to the primary section in the active workspace. NEVER ask the teacher for section IDs.'),
            'section_name' => $schema->string()->description('Optional name of the section if ID is unknown.'),
            'max_points' => $schema->number()->description('Max possible points (defaults to 100).'),
            'term' => $schema->string()->description('Optional grading term (e.g. "Prelim", "Midterm", "Final"). Auto-inferred or defaults to "Midterm". NEVER ask the teacher for terms.'),
            'due_date' => $schema->string()->description('Optional due date, e.g. "2026-10-20". Defaults to 7 days from now.'),
            'description' => $schema->string()->description('Optional activity instructions.'),
        ];
    }
}
