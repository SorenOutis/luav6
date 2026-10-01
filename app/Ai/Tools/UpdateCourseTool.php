<?php

namespace App\Ai\Tools;

use App\Models\Course;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class UpdateCourseTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'update_course';
    }

    public function description(): Stringable|string
    {
        return 'Prepare updates to an existing course in the active workspace for human review. This tool never updates the course directly; only the administrator approving in the UI can execute it.';
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
            ->first();

        if (! $course) {
            return "Error: course with ID {$courseId} not found in this workspace. Check courses_admin for valid course IDs.";
        }

        $changes = [];
        $preview = [];

        if (isset($request['name']) && trim((string) $request['name']) !== '') {
            $newName = trim((string) $request['name']);
            if ($newName !== $course->name) {
                $changes['name'] = $newName;
                $preview[] = ['field' => 'Name', 'before' => $course->name, 'after' => $newName];
            }
        }

        if (isset($request['description'])) {
            $newDesc = trim((string) $request['description']) ?: null;
            if ($newDesc !== $course->description) {
                $changes['description'] = $newDesc;
                $preview[] = ['field' => 'Description', 'before' => $course->description ?? 'None', 'after' => $newDesc ?? 'None'];
            }
        }

        if (isset($request['total_lessons'])) {
            $newLessons = max(1, (int) $request['total_lessons']);
            if ($newLessons !== (int) $course->total_lessons) {
                $changes['total_lessons'] = $newLessons;
                $preview[] = ['field' => 'Total Lessons', 'before' => (string) $course->total_lessons, 'after' => (string) $newLessons];
            }
        }

        if ($changes === []) {
            return "No changes were specified for course \"{$course->name}\" (#{$course->id}).";
        }

        $payload = [
            'course_id' => $course->id,
            'changes' => $changes,
            'expected_updated_at' => $course->updated_at?->toJSON(),
        ];

        return $this->stageAction(
            'update_course',
            'Update course',
            "Update course \"{$course->name}\" (#{$course->id}).",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'course_id' => $schema->integer()->description('The ID of the course to update.')->required(),
            'name' => $schema->string()->description('Optional updated course name.'),
            'description' => $schema->string()->description('Optional updated course description.'),
            'total_lessons' => $schema->integer()->description('Optional updated lesson count.'),
        ];
    }
}
