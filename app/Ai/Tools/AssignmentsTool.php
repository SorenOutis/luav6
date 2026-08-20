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
        return 'Get the list of assignments for the current user, including the sections they were assigned to, their due dates and submission status.';
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

        // Assignments are targeted at sections; students see the ones given
        // to a section they belong to, plus any they already interacted with
        // (pivot rows hold their submission state).
        $interacted = $user->assignments()->get()->keyBy('id');
        $sectionIds = $user->sections()->pluck('sections.id');

        $assignments = Assignment::query()
            ->with(['course:id,name', 'sections:id,name'])
            ->where(fn ($query) => $query
                ->whereHas('sections', fn ($sections) => $sections->whereIn('sections.id', $sectionIds))
                ->orWhereIn('id', $interacted->keys()))
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->limit(15)
            ->get()
            ->map(function ($assignment) use ($interacted) {
                $pivot = $interacted->get($assignment->id)?->pivot;

                return [
                    'title' => $assignment->title,
                    'course' => $assignment->course?->name,
                    'sections' => $assignment->sections->pluck('name')->values(),
                    'due_date' => $assignment->due_date?->toDateTimeString(),
                    'submitted' => (bool) ($pivot?->submitted ?? false),
                    'status' => $pivot?->status,
                    'grade' => $pivot?->grade,
                ];
            })
            ->values();

        if ($assignments->isEmpty()) {
            return 'The student has no assignments for their sections right now.';
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
