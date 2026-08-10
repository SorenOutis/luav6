<?php

namespace App\Ai\Tools;

use App\Models\Assignment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AssignmentsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the list of assignments for the current user, including their due dates and submission status.';
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

        // Assignments live on courses; students see the ones for their
        // courses plus any they already interacted with (pivot rows hold
        // their submission state).
        $interacted = $user->assignments()->get()->keyBy('id');
        $courseIds = $user->courses()->pluck('courses.id');

        $assignments = Assignment::query()
            ->with('course:id,name')
            ->where(fn ($query) => $query
                ->whereIn('course_id', $courseIds)
                ->orWhereIn('id', $interacted->keys()))
            ->orderBy('due_date')
            ->limit(15)
            ->get()
            ->map(function ($assignment) use ($interacted) {
                $pivot = $interacted->get($assignment->id)?->pivot;

                return [
                    'title' => $assignment->title,
                    'course' => $assignment->course?->name,
                    'due_date' => $assignment->due_date,
                    'submitted' => (bool) ($pivot?->submitted ?? false),
                    'status' => $pivot?->status,
                    'grade' => $pivot?->grade,
                ];
            })
            ->values();

        if ($assignments->isEmpty()) {
            return 'The student has no assignments for their courses right now.';
        }

        return json_encode($assignments);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
