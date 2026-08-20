<?php

namespace App\Ai\Tools;

use App\Models\Course;
use App\Models\Section;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateAssignmentTool extends PendingWriteTool implements Tool
{
    public function description(): Stringable|string
    {
        return 'Prepare an assignment for human review. This tool never creates the assignment directly. It creates a server-issued approval card showing the exact sections, title, due date, and instructions; only a UI approval can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $title = Str::limit(trim((string) ($request['title'] ?? '')), 255, '');
        if ($title === '') {
            return 'Error: a title is required.';
        }

        // Accepted as a comma-separated list ("3,7") or a plain array,
        // whichever the model produces.
        $rawSections = $request['section_ids'] ?? '';
        $sectionIds = collect(is_array($rawSections) ? $rawSections : explode(',', (string) $rawSections))
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter()
            ->unique()
            ->values();

        if ($sectionIds->isEmpty()) {
            return 'Error: at least one section_id is required. An assignment with no sections reaches no students. Use workspace_overview for valid section IDs.';
        }

        $sections = Section::query()
            ->withoutGlobalScope('workspace')
            ->whereIn('id', $sectionIds)
            ->where('workspace_id', $this->workspaceId())
            ->get();

        $missing = $sectionIds->diff($sections->pluck('id'));
        if ($missing->isNotEmpty()) {
            return 'Error: section(s) ['.$missing->implode(', ').'] do not exist in this workspace. Use workspace_overview for valid section IDs.';
        }

        // The course is an optional label; targeting is done by section.
        $course = null;
        if (! empty($request['course_id'])) {
            $course = Course::query()
                ->withoutGlobalScope('workspace')
                ->whereKey((int) $request['course_id'])
                ->where('workspace_id', $this->workspaceId())
                ->first();
            if (! $course) {
                return 'Error: course not found in this workspace. Use workspace_overview for valid course IDs, or omit course_id.';
            }
        }

        try {
            $dueDate = Carbon::parse((string) ($request['due_date'] ?? ''));
        } catch (\Throwable) {
            return 'Error: due_date must be valid, e.g. "2026-08-25" or "2026-08-25 23:59".';
        }

        $description = trim((string) ($request['description'] ?? '')) ?: null;
        if ($description !== null && mb_strlen($description) > 20000) {
            return 'Error: assignment instructions are too long (maximum 20,000 characters).';
        }

        $sectionLabel = $sections
            ->map(fn (Section $section) => "{$section->name} (#{$section->id})")
            ->implode(', ');

        return $this->stageAction(
            'create_assignment',
            'Create assignment',
            "Create \"{$title}\" for {$sectionLabel}.",
            [
                'title' => $title,
                'description' => $description,
                'due_date' => $dueDate->toIso8601String(),
                'section_ids' => $sections->pluck('id')->all(),
                'section_expected_updated_at' => $sections
                    ->mapWithKeys(fn (Section $section) => [$section->id => $section->updated_at?->toJSON()])
                    ->all(),
                'course_id' => $course?->id,
                'course_expected_updated_at' => $course?->updated_at?->toJSON(),
            ],
            [
                ['field' => 'Record', 'before' => null, 'after' => 'New assignment'],
                ['field' => 'Title', 'before' => null, 'after' => $title],
                ['field' => 'Sections', 'before' => null, 'after' => $sectionLabel],
                ['field' => 'Course', 'before' => null, 'after' => $course ? "{$course->name} (#{$course->id})" : 'None'],
                ['field' => 'Due date', 'before' => null, 'after' => $dueDate->format('M d, Y g:i A')],
                ['field' => 'Instructions', 'before' => null, 'after' => $description],
            ],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Assignment title.')->required(),
            'section_ids' => $schema->string()
                ->description('Comma-separated section IDs from workspace_overview that receive this assignment, e.g. "3" or "3,7". At least one is required.')
                ->required(),
            'due_date' => $schema->string()->description('Due date, e.g. "2026-08-25" or "2026-08-25 23:59".')->required(),
            'course_id' => $schema->integer()->description('Optional course ID from workspace_overview, used as a label only.'),
            'description' => $schema->string()->description('Optional assignment instructions.'),
        ];
    }
}
