<?php

namespace App\Jobs;

use App\Enums\EssayGradingMethod;
use App\Models\AiEssayFeedbackDraft;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Services\AIService;
use App\Services\ExamXpAwardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** Grade automatic essays off the web request and apply the result immediately. */
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

    public function handle(AIService $aiService): void
    {
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

        $essays = [];
        $sourceHashes = [];
        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;
            if ($this->onlyQuestionNumber !== null && $questionNumber !== $this->onlyQuestionNumber) {
                continue;
            }
            if (($question['type'] ?? null) !== 'essay') {
                continue;
            }

            $answer = collect($answers)->firstWhere('question_number', $questionNumber);
            if (! is_array($answer)) {
                continue;
            }

            $gradingMethod = array_key_exists('grading_method', $answer)
                ? EssayGradingMethod::forAnswer($answer)
                : EssayGradingMethod::forQuestion($question);
            if ($gradingMethod !== EssayGradingMethod::Ai) {
                continue;
            }

            $text = trim((string) ($answer['answer'] ?? ''));
            if ($text === '') {
                continue;
            }

            $hasScore = array_key_exists('ai_score', $answer);
            $hasFeedback = trim((string) ($answer['ai_feedback'] ?? '')) !== '';
            if (! $this->forceRegenerate && $hasScore && $hasFeedback) {
                continue;
            }

            $essays[$questionNumber] = [
                'essayText' => $text,
                'questionText' => (string) ($answer['question_text'] ?? $question['text'] ?? ''),
                'maxPoints' => (int) ($answer['points'] ?? $question['points'] ?? $part->points ?? 1),
                'feedbackOnly' => ! $this->forceRegenerate && $hasScore,
                'includeFeedback' => true,
            ];
            $sourceHashes[$questionNumber] = $this->answerSourceHash($answer);
        }

        if ($essays === []) {
            $result = DB::transaction(function () use ($submission): array {
                $lockedSubmission = ExamSubmission::query()->lockForUpdate()->find($submission->id);
                if (! $lockedSubmission) {
                    return ['submission' => null, 'status' => null];
                }

                $status = $this->completionStatus(
                    $lockedSubmission->answers,
                    $lockedSubmission->status === 'graded',
                );
                $lockedSubmission->forceFill([
                    'status' => $status,
                    'grading_failed' => $status === 'pending_ai'
                        ? (bool) $lockedSubmission->grading_failed
                        : false,
                ])->save();

                return ['submission' => $lockedSubmission, 'status' => $status];
            });

            if ($result['submission']) {
                $this->awardXpWhenComplete($result['submission'], (string) $result['status']);
            }

            return;
        }

        $assessments = $aiService->batchAssessEssays($essays);
        $result = DB::transaction(function () use ($submission, $assessments, $essays, $sourceHashes): array {
            $lockedSubmission = ExamSubmission::query()->lockForUpdate()->find($submission->id);
            if (! $lockedSubmission) {
                return [
                    'submission' => null,
                    'status' => null,
                ];
            }

            $scoreDelta = 0.0;
            $gradingFailed = false;
            $appliedQuestionNumbers = [];
            $currentAnswers = $lockedSubmission->answers;
            $manualGradingWasCompleted = $lockedSubmission->status === 'graded';

            $updatedAnswers = collect($currentAnswers)->map(function ($answer) use (
                $assessments,
                $essays,
                $sourceHashes,
                &$scoreDelta,
                &$gradingFailed,
                &$appliedQuestionNumbers,
            ) {
                if (! is_array($answer)) {
                    return $answer;
                }

                $questionNumber = (int) ($answer['question_number'] ?? 0);
                if (! array_key_exists($questionNumber, $essays)) {
                    return $answer;
                }

                if (! hash_equals((string) ($sourceHashes[$questionNumber] ?? ''), $this->answerSourceHash($answer))) {
                    $gradingFailed = true;

                    return $answer;
                }

                $assessment = $assessments[$questionNumber] ?? null;
                if (! is_array($assessment) || ! array_key_exists('score', $assessment)) {
                    $gradingFailed = true;

                    return $answer;
                }

                $maxPoints = max(0.0, (float) ($answer['points'] ?? $essays[$questionNumber]['maxPoints'] ?? 1));
                $oldScore = array_key_exists('ai_score', $answer) ? (float) $answer['ai_score'] : 0.0;
                $newScore = max(0.0, min($maxPoints, (float) $assessment['score']));
                $feedback = trim((string) ($assessment['feedback'] ?? ''));

                $answer['ai_score'] = $newScore;
                $scoreDelta += $newScore - $oldScore;

                if ($feedback === '') {
                    $gradingFailed = true;

                    return $answer;
                }

                $answer['ai_feedback'] = $feedback;
                $answer['ai_feedback_source'] = 'automatic';
                unset($answer['ai_feedback_review_id']);
                $appliedQuestionNumbers[] = $questionNumber;

                return $answer;
            })->values()->all();

            $status = $this->completionStatus($updatedAnswers, $manualGradingWasCompleted);
            $lockedSubmission->forceFill([
                'answers' => $updatedAnswers,
                'score' => round((float) $lockedSubmission->score + $scoreDelta, 2),
                'grading_failed' => $gradingFailed,
                'status' => $status,
            ])->save();

            if ($appliedQuestionNumbers !== []) {
                AiEssayFeedbackDraft::query()
                    ->withoutGlobalScope('workspace')
                    ->where('exam_submission_id', $lockedSubmission->id)
                    ->whereIn('question_number', array_unique($appliedQuestionNumbers))
                    ->where('review_status', '!=', AiEssayFeedbackDraft::STATUS_APPROVED)
                    ->update([
                        'review_status' => AiEssayFeedbackDraft::STATUS_SUPERSEDED,
                        'last_error' => 'The score and feedback were applied automatically by the current grading workflow.',
                        'updated_at' => now(),
                    ]);
            }

            return [
                'submission' => $lockedSubmission,
                'status' => $status,
            ];
        });

        if (! $result['submission']) {
            return;
        }

        /** @var ExamSubmission $submission */
        $submission = $result['submission'];
        $status = (string) $result['status'];

        $this->awardXpWhenComplete($submission, $status);
    }

    public function failed(?\Throwable $e): void
    {
        Log::error("Essay grading failed for submission {$this->submissionId}: ".($e?->getMessage() ?? 'unknown'));

        ExamSubmission::query()
            ->where('id', $this->submissionId)
            ->whereIn('status', ['pending_review', 'pending_ai'])
            ->update([
                'status' => 'pending_ai',
                'grading_failed' => true,
            ]);
    }

    /** @param array<string, mixed> $answer */
    private function answerSourceHash(array $answer): string
    {
        return hash('sha256', (string) json_encode([
            'question_number' => (int) ($answer['question_number'] ?? 0),
            'question_text' => (string) ($answer['question_text'] ?? ''),
            'answer' => (string) ($answer['answer'] ?? ''),
            'points' => (float) ($answer['points'] ?? 0),
            'grading_method' => EssayGradingMethod::forAnswer($answer)->value,
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE));
    }

    /** @param array<int, mixed> $answers */
    private function completionStatus(array $answers, bool $manualGradingWasCompleted): string
    {
        $hasPendingManualEssay = false;

        foreach ($answers as $answer) {
            if (
                ! is_array($answer)
                || ($answer['question_type'] ?? null) !== 'essay'
                || trim((string) ($answer['answer'] ?? '')) === ''
            ) {
                continue;
            }

            if (EssayGradingMethod::forAnswer($answer) === EssayGradingMethod::Manual) {
                $hasPendingManualEssay = true;

                continue;
            }

            if (
                ! array_key_exists('ai_score', $answer)
                || trim((string) ($answer['ai_feedback'] ?? '')) === ''
            ) {
                return 'pending_ai';
            }
        }

        if ($hasPendingManualEssay && ! $manualGradingWasCompleted) {
            return 'pending_review';
        }

        return 'graded';
    }

    private function awardXpWhenComplete(ExamSubmission $submission, string $status): void
    {
        if ($status !== 'graded') {
            return;
        }

        $submission->loadMissing(['user', 'exam']);
        app(ExamXpAwardService::class)->awardIfEligible($submission->user, $submission->exam);
    }
}
