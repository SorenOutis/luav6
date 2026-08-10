<?php

namespace App\Ai\Tools;

use App\Models\Exam;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ExamsAdminTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List the workspace\'s exams with their IDs, dates, status, submission counts, and average scores. Limited to the admin\'s own workspace.';
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

        $exams = Exam::query()
            ->with('section:id,name')
            ->withCount('submissions')
            ->withAvg('submissions', 'score')
            ->latest('exam_date')
            ->limit(10)
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'exam_date' => $exam->exam_date?->format('M d, Y g:i A'),
                'status' => $exam->status,
                'section' => $exam->section?->name ?? 'All sections',
                'submissions' => $exam->submissions_count,
                'average_score' => $exam->submissions_avg_score !== null
                    ? round((float) $exam->submissions_avg_score, 1)
                    : null,
            ])
            ->values();

        if ($exams->isEmpty()) {
            return 'There are no exams in this workspace yet.';
        }

        return json_encode($exams);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
