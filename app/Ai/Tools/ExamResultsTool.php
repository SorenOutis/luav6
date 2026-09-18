<?php

namespace App\Ai\Tools;

use App\Models\ExamSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExamResultsTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the current student\'s recent exam results — exam title, score, grading status, lateness, and a feedback excerpt.';
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

        $submissions = $user->examSubmissions()
            ->with('exam:id,title')
            ->whereIn('status', ['graded', 'pending_ai', 'pending_review'])
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (ExamSubmission $submission) => [
                'exam' => $submission->exam?->title ?? 'Unknown exam',
                'status' => $submission->status,
                'score' => $submission->score !== null ? (float) $submission->score : null,
                'is_late' => (bool) $submission->is_late,
                'feedback' => $submission->feedback ? Str::limit((string) $submission->feedback, 200) : null,
                'when' => $submission->updated_at?->diffForHumans(),
            ])
            ->values();

        if ($submissions->isEmpty()) {
            return 'The student has no graded or submitted exam results yet.';
        }

        return json_encode($submissions);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
