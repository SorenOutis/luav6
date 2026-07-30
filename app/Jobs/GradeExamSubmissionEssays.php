<?php

namespace App\Jobs;

use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase 1.0.2 — grade a submission's essays off the web request.
 *
 * Previously ExamController::submitPart() called the AI provider inline, before
 * persisting. Each essay is a ≤45s HTTP call and Http::pool() fires them all at
 * once, so a class submitting together starved the RoadRunner worker pool and
 * requests died *before* the answers were written. Students lost completed work.
 *
 * Now the submission is saved first with its auto-gradable score, and this job
 * adds the essay marks and AI feedback afterwards.
 */
class GradeExamSubmissionEssays implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Retry a couple of times — provider blips are common on school wifi. */
    public int $tries = 3;

    /** Generous: Http::pool of several essays at 45s each. */
    public int $timeout = 300;

    public function __construct(public int $submissionId)
    {
        $this->onQueue('ai');
    }

    /** Back off 10s, then 30s, before giving up. */
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

        $answers = $submission->answers; // cast returns an array
        $questions = is_array($part->questions) ? $part->questions : [];

        $essays = [];
        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;

            if (($question['type'] ?? null) !== 'essay') {
                continue;
            }

            $answer = collect($answers)->firstWhere('question_number', $questionNumber);
            $text = $answer['answer'] ?? null;

            if ($text === null || trim((string) $text) === '') {
                continue;
            }

            // Idempotency: a retry (or a duplicate dispatch) must not score the
            // same essay twice. Existing scores without feedback are still sent
            // as feedback-only work so legacy submissions can finish.
            $hasFeedback = trim((string) ($answer['ai_feedback'] ?? '')) !== '';
            if (array_key_exists('ai_score', $answer) && $hasFeedback) {
                continue;
            }

            $essays[$questionNumber] = [
                'essayText' => (string) $text,
                'questionText' => (string) ($question['text'] ?? ''),
                'maxPoints' => (int) ($question['points'] ?? $part->points ?? 1),
                'feedbackOnly' => array_key_exists('ai_score', $answer),
                'includeFeedback' => true,
            ];
        }

        if ($essays === []) {
            // Nothing to score (e.g. every essay left blank).
            $submission->forceFill(['status' => 'graded'])->save();

            return;
        }

        $assessments = $aiService->batchAssessEssays($essays);

        // A provider failure yields score 0.0, which is indistinguishable from a
        // genuinely zero-scoring essay. Track it so the teacher can re-run
        // rather than silently handing a student a zero (Phase 1.0.7).
        $gradingFailed = false;
        $essayScore = 0.0;

        $updatedAnswers = collect($answers)->map(function ($answer) use ($assessments, &$essayScore, &$gradingFailed, $essays) {
            $number = $answer['question_number'] ?? null;

            if ($number === null || ! array_key_exists($number, $assessments)) {
                // Phase 1.0.7 — if this answer is an essay question that the
                // provider skipped, flag the submission so the teacher can re-run.
                if ($number !== null && array_key_exists($number, $essays)) {
                    $gradingFailed = true;
                }

                return $answer;
            }

            $score = (float) ($assessments[$number]['score'] ?? 0.0);
            $hasExistingScore = array_key_exists('ai_score', $answer);
            if (! $hasExistingScore) {
                $essayScore += $score;
            }

            if (! isset($assessments[$number]['score'])) {
                $gradingFailed = true;
            }

            $assessment = $assessments[$number];
            $updatedAnswer = array_merge($answer, ['ai_score' => $score]);
            $feedback = trim((string) ($assessment['feedback'] ?? ''));

            if ($feedback !== '') {
                $updatedAnswer['ai_feedback'] = $feedback;
                $updatedAnswer['ai_feedback_source'] = 'automatic';
            } else {
                $gradingFailed = true;
            }

            return $updatedAnswer;
        })->values()->all();

        // The stored score already holds the auto-gradable marks; add the essays
        // and automatic written feedback. Any missing AI result remains visible
        // as pending_ai instead of being silently treated as graded.
        $allEssayFeedbackComplete = collect($updatedAnswers)
            ->filter(fn ($answer) => ($answer['question_type'] ?? null) === 'essay')
            ->every(fn ($answer) => array_key_exists('ai_score', $answer)
                && trim((string) ($answer['ai_feedback'] ?? '')) !== '');

        $submission->forceFill([
            'answers' => $updatedAnswers,
            'score' => round((float) $submission->score + $essayScore, 2),
            'grading_failed' => $gradingFailed,
            'status' => $allEssayFeedbackComplete ? 'graded' : 'pending_ai',
        ])->save();
    }

    /**
     * All retries exhausted — leave the submission visible to the teacher as
     * needing attention rather than stuck in pending_review forever.
     */
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
}
