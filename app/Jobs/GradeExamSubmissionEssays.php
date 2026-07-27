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
 * adds the essay marks afterwards.
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
            // same essay twice. Status can't be used as the guard because this
            // job deliberately leaves it at 'pending_review' for the teacher's
            // feedback pass, so key off ai_score already being present.
            if (array_key_exists('ai_score', $answer)) {
                continue;
            }

            $essays[$questionNumber] = [
                'essayText' => (string) $text,
                'questionText' => (string) ($question['text'] ?? ''),
                'maxPoints' => (int) ($question['points'] ?? $part->points ?? 1),
            ];
        }

        if ($essays === []) {
            // Nothing to score (e.g. every essay left blank).
            $submission->forceFill(['status' => 'pending_review'])->save();

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
            $essayScore += $score;

            if (! isset($assessments[$number]['score'])) {
                $gradingFailed = true;
            }

            // array_merge preserves question_text / question_type / points,
            // which GenerateExamEssayFeedback reads when the teacher later runs
            // the feedback pass. Do not rebuild this array from scratch.
            return array_merge($answer, ['ai_score' => $score]);
        })->values()->all();

        // The stored score already holds the auto-gradable marks; add the essays.
        // ⚠️ Status stays 'pending_review', NOT 'graded'.
        //
        // This job only produces scores. Written feedback is a separate,
        // teacher-triggered pass (GenerateExamEssayFeedback) run from the admin
        // panel, and that flow owns the transition to 'graded'. Marking the
        // submission graded here would tell the teacher it is finished when no
        // feedback exists yet.
        $submission->forceFill([
            'answers' => $updatedAnswers,
            'score' => round((float) $submission->score + $essayScore, 2),
            'grading_failed' => $gradingFailed,
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
            ->where('status', 'pending_review')
            ->update(['grading_failed' => true]);
    }
}
