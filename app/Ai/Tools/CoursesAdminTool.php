<?php

namespace App\Ai\Tools;

use App\Models\Course;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CoursesAdminTool implements Tool
{
    public function name(): string
    {
        return 'courses_admin';
    }

    public function description(): Stringable|string
    {
        return 'List all courses in the active workspace with course ID, title, description, module count, and total lessons.';
    }

    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();
        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        $workspaceId = app(WorkspaceContext::class)->id();

        $courses = Course::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->withCount('modules')
            ->orderBy('name')
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'name' => $course->name,
                'description' => $course->description,
                'modules_count' => $course->modules_count,
                'total_lessons' => (int) ($course->total_lessons ?? 0),
            ])
            ->values();

        if ($courses->isEmpty()) {
            return 'There are no courses in this workspace yet. You can create one using create_course.';
        }

        return (string) json_encode($courses);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
