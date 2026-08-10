<?php

namespace App\Ai\Tools;

use App\Models\Assignment;
use App\Models\Course;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateAssignmentTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Create an assignment for a course in the admin\'s workspace; it becomes visible to students on their Assignments page. IMPORTANT: present the summary to the admin first and only call this with confirm=true after they explicitly approve.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();

        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        if (! $request['confirm']) {
            return 'NOT EXECUTED — confirmation missing. Present the assignment summary to the admin and ask them to confirm; then call this tool again with confirm=true.';
        }

        $title = trim((string) ($request['title'] ?? ''));

        if ($title === '') {
            return 'Error: a title is required.';
        }

        // The workspace global scope makes this null for courses owned by
        // another admin.
        $course = Course::query()->find((int) ($request['course_id'] ?? 0));

        if (! $course) {
            return 'Error: course not found in this workspace. Use the workspace_overview tool to list valid course IDs.';
        }

        try {
            $dueDate = Carbon::parse((string) ($request['due_date'] ?? ''));
        } catch (\Throwable) {
            return 'Error: due_date must be a valid date, e.g. "2026-08-25" or "2026-08-25 23:59".';
        }

        $assignment = Assignment::create([
            'title' => Str::limit($title, 255, ''),
            'description' => trim((string) ($request['description'] ?? '')) ?: null,
            'due_date' => $dueDate,
            'course_id' => $course->id,
        ]);

        return "Assignment created: \"{$assignment->title}\" (ID {$assignment->id}) for course \"{$course->name}\", due {$dueDate->format('M d, Y')}. It is now visible to students on the Assignments page.";
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Assignment title.')->required(),
            'course_id' => $schema->integer()->description('Course ID from workspace_overview.')->required(),
            'due_date' => $schema->string()->description('Due date, e.g. "2026-08-25" or "2026-08-25 23:59".')->required(),
            'description' => $schema->string()->description('Optional instructions for the assignment.'),
            'confirm' => $schema->boolean()->description('Must be true, and only after the admin explicitly approved the summary.')->required(),
        ];
    }
}
