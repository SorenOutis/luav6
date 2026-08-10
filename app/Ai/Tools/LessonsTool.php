<?php

namespace App\Ai\Tools;

use App\Models\Course;
use App\Models\LessonUserProgress;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class LessonsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the current student\'s enrolled courses with lesson progress and the next incomplete lesson in each course.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $user = auth()->user();

        if (! $user) {
            return 'No user is currently authenticated.';
        }

        $courses = $user->courses()
            ->with(['modules' => fn ($query) => $query->orderBy('sort_order'), 'modules.lessons'])
            ->get()
            ->map(function (Course $course) use ($user) {
                $lessons = $course->modules->flatMap->lessons->values();

                $completedIds = LessonUserProgress::query()
                    ->where('user_id', $user->id)
                    ->whereIn('lesson_id', $lessons->pluck('id'))
                    ->where('completed', true)
                    ->pluck('lesson_id');

                $next = $lessons->first(fn ($lesson) => ! $completedIds->contains($lesson->id));

                return [
                    'course' => $course->name,
                    'total_lessons' => $lessons->count(),
                    'completed_lessons' => $completedIds->count(),
                    'next_lesson' => $next?->title,
                ];
            })
            ->values();

        if ($courses->isEmpty()) {
            return 'The student is not enrolled in any courses.';
        }

        return json_encode($courses);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
