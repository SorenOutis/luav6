<?php

namespace App\Ai\Tools;

use App\Models\ExamSubmission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GradeSubmissionTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'grade_submission';
    }

    public function description(): Stringable|string
    {
        return 'Prepare grading (score and teacher feedback) for an exam submission for human review. This tool never updates the submission directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $submissionId = (int) ($request['submission_id'] ?? 0);
        $submission = ExamSubmission::query()
            ->whereKey($submissionId)
            ->whereHas('exam', fn ($q) => $q->where('workspace_id', $this->workspaceId()))
            ->with(['user:id,name', 'exam:id,title'])
            ->first();

        if (! $submission) {
            return "Error: exam submission #{$submissionId} not found in this workspace. Use submissions_to_grade to find submissions waiting for grading.";
        }

        if (! isset($request['score']) || ! is_numeric($request['score'])) {
            return 'Error: score is required and must be a number.';
        }

        $score = (float) $request['score'];
        $feedback = trim((string) ($request['feedback'] ?? '')) ?: null;

        $studentName = $submission->user?->name ?? "Student #{$submission->user_id}";
        $examTitle = $submission->exam?->title ?? "Exam #{$submission->exam_id}";

        $payload = [
            'submission_id' => $submission->id,
            'expected_updated_at' => $submission->updated_at?->toJSON(),
            'score' => $score,
            'feedback' => $feedback,
            'status' => 'graded',
        ];

        $preview = [
            ['field' => 'Submission ID', 'before' => (string) $submission->id, 'after' => (string) $submission->id],
            ['field' => 'Student', 'before' => $studentName, 'after' => $studentName],
            ['field' => 'Exam', 'before' => $examTitle, 'after' => $examTitle],
            ['field' => 'Status', 'before' => $submission->status, 'after' => 'graded'],
            ['field' => 'Score', 'before' => $submission->score !== null ? (string) $submission->score : 'Ungraded', 'after' => (string) $score],
            ['field' => 'Feedback', 'before' => $submission->feedback ?? 'None', 'after' => $feedback ?? 'None'],
        ];

        return $this->stageAction(
            'grade_submission',
            'Grade exam submission',
            "Set score to {$score} for {$studentName}'s submission on \"{$examTitle}\".",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'submission_id' => $schema->integer()->required(),
            'score' => $schema->number()->required(),
            'feedback' => $schema->string(),
        ];
    }
}
