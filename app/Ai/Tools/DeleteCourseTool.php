<?php

namespace App\Ai\Tools;

use App\Models\Course;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteCourseTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'delete_course';
    }

    public function description(): Stringable|string
    {
        return 'Prepare deletion of a course from the active workspace for human review. This tool never deletes the course directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $courseId = (int) ($request['course_id'] ?? 0);
        $course = Course::query()
            ->withoutGlobalScope('workspace')
            ->whereKey($courseId)
            ->where('workspace_id', $this->workspaceId())
            ->withCount('modules')
            ->first();

        if (! $course) {
            return "Error: course with ID {$courseId} not found in this workspace. Check courses_admin for valid course IDs.";
        }

        $payload = [
            'course_id' => $course->id,
            'expected_updated_at' => $course->updated_at?->toJSON(),
        ];

        $preview = [
            ['field' => 'Course ID', 'before' => (string) $course->id, 'after' => 'Deleted'],
            ['field' => 'Course Name', 'before' => $course->name, 'after' => 'Deleted'],
            ['field' => 'Modules Linked', 'before' => (string) $course->modules_count, 'after' => 'Removed'],
        ];

        return $this->stageAction(
            'delete_course',
            'Delete course',
            "Delete course \"{$course->name}\" (ID {$course->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'course_id' => $schema->integer()->description('The ID of the course to delete.')->required(),
        ];
    }
}
