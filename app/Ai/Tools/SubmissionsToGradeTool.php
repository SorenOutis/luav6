<?php

namespace App\Ai\Tools;

use App\Models\ExamSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SubmissionsToGradeTool implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List exam submissions in the admin\'s workspace that are waiting for grading (pending AI grading, pending review, or where grading failed) — student, exam, status, and when submitted.';
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

        $submissions = ExamSubmission::query()
            ->with(['user:id,name', 'exam:id,title'])
            ->where(fn ($query) => $query
                ->whereIn('status', ['pending_ai', 'pending_review'])
                ->orWhere('grading_failed', true))
            ->whereHas('exam')
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (ExamSubmission $submission) => [
                'id' => $submission->id,
                'student' => $submission->user?->name ?? 'Unknown student',
                'exam' => $submission->exam?->title ?? 'Unknown exam',
                'status' => $submission->status,
                'grading_failed' => (bool) $submission->grading_failed,
                'when' => $submission->updated_at?->diffForHumans(),
            ])
            ->values();

        if ($submissions->isEmpty()) {
            return 'Nothing is waiting for grading — all submissions are up to date.';
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
