<?php

namespace App\Ai\Tools;

use App\Models\Grade;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GradesTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the current student\'s recorded grades per subject and grading period, with percentages and remarks.';
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

        $grades = $user->grades()
            ->with('section:id,name')
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (Grade $grade) => [
                'subject' => $grade->subject,
                'period' => $grade->period,
                'score' => (float) $grade->score,
                'max_score' => (float) $grade->max_score,
                'percentage' => $grade->percentage,
                'section' => $grade->section?->name,
                'remarks' => $grade->remarks,
            ])
            ->values();

        if ($grades->isEmpty()) {
            return 'The student has no recorded grades yet.';
        }

        return json_encode($grades);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
