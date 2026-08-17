<?php

namespace App\Jobs;

use App\Models\AiEssayFeedbackDraft;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Setting;
use App\Services\AIService;
use App\Services\AiReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/** Generate teacher-only essay score/feedback proposals off the web request. */
class GradeExamSubmissionEssays implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 300;

    public function __construct(
        public int $submissionId,
        public bool $forceRegenerate = false,
        public ?int $onlyQuestionNumber = null,
    ) {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return implode(':', [
            $this->submissionId,
            $this->forceRegenerate ? 'force' : 'normal',
            $this->onlyQuestionNumber ?? 'all',
        ]);
    }

    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(AIService $aiService, ?AiReviewService $reviews = null): void
    {
        $reviews ??= app(AiReviewService::class);
        $submission = ExamSubmission::query()->find($this->submissionId);
        if (! $submission) {
            return;
        }

        $part = ExamPart::query()->find($submission->exam_part_id);
        if (! $part) {
            return;
        }

        $answers = $submission->answers;
        $questions = is_array($part->questions) ? $part->questions : [];
        $existingDrafts = AiEssayFeedbackDraft::query()
            ->withoutGlobalScope('workspace')
            ->where('exam_submission_id', $submission->id)
            ->get()
            ->keyBy('question_number');

        $essays = [];
        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;
            if ($this->onlyQuestionNumber !== null && $questionNumber !== $this->onlyQuestionNumber) {
                continue;
            }
            if (($question['type'] ?? null) !== 'essay') {
                continue;
            }

            $answer = collect($answers)->firstWhere('question_number', $questionNumber);
            $text = is_array($answer) ? trim((string) ($answer['answer'] ?? '')) : '';
            if ($text === '') {
                continue;
            }

            $existingDraft = $existingDrafts->get($questionNumber);
            if ($existingDraft && ! $this->forceRegenerate) {
                // Awaiting, rejected, and approved proposals all require a
                // deliberate teacher action before another provider call.
                continue;
            }

            $hasFeedback = trim((string) ($answer['ai_feedback'] ?? '')) !== '';
            if (! $this->forceRegenerate && array_key_exists('ai_score', $answer) && $hasFeedback) {
                continue;
            }

            $essays[$questionNumber] = [
                'essayText' => $text,
                'questionText' => (string) ($question['text'] ?? ''),
                'maxPoints' => (int) ($question['points'] ?? $part->points ?? 1),
                'feedbackOnly' => array_key_exists('ai_score', $answer),
                'includeFeedback' => true,
            ];
        }

        if ($essays === []) {
            $submission->forceFill([
                'status' => $this->allEssaysApplied($answers) ? 'graded' : 'pending_review',
            ])->save();

            return;
        }

        $assessments = $aiService->batchAssessEssays($essays);
        $provider = (string) Setting::get('ai_provider', 'gemini');
        $staged = 0;
        $gradingFailed = false;

        foreach ($essays as $questionNumber => $essay) {
            $assessment = $assessments[$questionNumber] ?? null;
            if (
                ! is_array($assessment)
                || ! array_key_exists('score', $assessment)
                || trim((string) ($assessment['feedback'] ?? '')) === ''
            ) {
                $gradingFailed = true;

                continue;
            }

            $draft = $reviews->stageEssayFeedback(
                $submission,
                (int) $questionNumber,
                $assessment,
                provider: $provider,
                force: $this->forceRegenerate,
            );
            if ($draft) {
                $staged++;
            }
        }

        $submission->refresh();
        $submission->forceFill([
            'grading_failed' => $gradingFailed,
            'status' => $staged > 0 ? 'pending_review' : 'pending_ai',
        ])->save();
    }

    public function failed(?\Throwable $e): void
    {
        Log::error("Essay feedback generation failed for submission {$this->submissionId}: ".($e?->getMessage() ?? 'unknown'));

        ExamSubmission::query()
            ->where('id', $this->submissionId)
            ->whereIn('status', ['pending_review', 'pending_ai'])
            ->update([
                'status' => 'pending_ai',
                'grading_failed' => true,
            ]);

        AiEssayFeedbackDraft::query()
            ->withoutGlobalScope('workspace')
            ->where('exam_submission_id', $this->submissionId)
            ->where('review_status', AiEssayFeedbackDraft::STATUS_GENERATING)
            ->get()
            ->each(function (AiEssayFeedbackDraft $draft): void {
                $draft->forceFill([
                    'review_status' => AiEssayFeedbackDraft::STATUS_REJECTED,
                    'last_error' => 'AI regeneration failed. The previous proposal was not applied.',
                ])->save();
                $draft->reviewEvents()->create([
                    'workspace_id' => $draft->workspace_id,
                    'event' => 'regeneration_failed',
                    'version' => $draft->generation_version,
                    'notes' => 'The provider did not produce a replacement proposal.',
                ]);
            });
    }

    private function allEssaysApplied(array $answers): bool
    {
        return collect($answers)
            ->filter(fn ($answer): bool => is_array($answer)
                && ($answer['question_type'] ?? null) === 'essay'
                && trim((string) ($answer['answer'] ?? '')) !== '')
            ->every(fn ($answer): bool => array_key_exists('ai_score', $answer)
                && trim((string) ($answer['ai_feedback'] ?? '')) !== '');
    }
}
