<?php

namespace App\Ai\Tools;

use App\Models\Course;
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
        return 'Prepare a course assignment for human review. This tool never creates the assignment directly. It creates a server-issued approval card showing the exact course, title, due date, and instructions; only a UI approval can execute it.';
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

        $course = Course::query()
            ->withoutGlobalScope('workspace')
            ->whereKey((int) ($request['course_id'] ?? 0))
            ->where('workspace_id', $this->workspaceId())
            ->first();
        if (! $course) {
            return 'Error: course not found in this workspace. Use workspace_overview for valid course IDs.';
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

        return $this->stageAction(
            'create_assignment',
            'Create assignment',
            "Create \"{$title}\" for {$course->name}.",
            [
                'title' => $title,
                'description' => $description,
                'due_date' => $dueDate->toIso8601String(),
                'course_id' => $course->id,
                'course_expected_updated_at' => $course->updated_at?->toJSON(),
            ],
            [
                ['field' => 'Record', 'before' => null, 'after' => 'New assignment'],
                ['field' => 'Title', 'before' => null, 'after' => $title],
                ['field' => 'Course', 'before' => null, 'after' => "{$course->name} (#{$course->id})"],
                ['field' => 'Due date', 'before' => null, 'after' => $dueDate->format('M d, Y g:i A')],
                ['field' => 'Instructions', 'before' => null, 'after' => $description],
            ],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Assignment title.')->required(),
            'course_id' => $schema->integer()->description('Course ID from workspace_overview.')->required(),
            'due_date' => $schema->string()->description('Due date, e.g. "2026-08-25" or "2026-08-25 23:59".')->required(),
            'description' => $schema->string()->description('Optional assignment instructions.'),
        ];
    }
}
