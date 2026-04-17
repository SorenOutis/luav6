<?php

namespace App\Ai\Tools;

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

        $assignments = $user->assignments()->get()->map(function ($assignment) {
            return [
                'title' => $assignment->title,
                'due_date' => $assignment->due_date,
                'status' => $assignment->pivot->status,
                'submitted' => (bool) $assignment->pivot->submitted,
                'grade' => $assignment->pivot->grade,
            ];
        });

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
