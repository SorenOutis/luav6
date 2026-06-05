<?php

namespace App\Jobs;

use App\Models\Exam;
use App\Models\ExamAiFeedbackRun;
use App\Models\ExamSubmission;
use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class GenerateExamEssayFeedback implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $examId)
    {
        $this->onQueue('ai');
    }

    public function handle(AIService $aiService): void
    {
        $exam = Exam::query()->find($this->examId);
        if (! $exam) {
            return;
        }

        $run = ExamAiFeedbackRun::query()->create([
            'exam_id' => $exam->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            // Pre-count actionable essays (missing feedback/score and below retry cap).
            $total = 0;
            ExamSubmission::query()
                ->where('exam_id', $exam->id)
                ->select(['id', 'answers'])
                ->orderBy('id')
                ->chunkById(200, function ($submissions) use (&$total) {
                    /** @var Collection<int, ExamSubmission> $submissions */
                    foreach ($submissions as $submission) {
                        $answers = is_array($submission->answers)
                            ? $submission->answers
                            : json_decode($submission->answers ?? '[]', true) ?? [];

                        if (! is_array($answers)) {
                            continue;
                        }

                        foreach ($answers as $answer) {
                            if (! is_array($answer)) {
                                continue;
                            }
                            if (! self::isEssayCandidate($answer)) {
                                continue;
                            }
                            if (! self::needsAiCompletion($answer)) {
                                continue;
                            }
                            $attempts = (int) ($answer['ai_feedback_attempts'] ?? 0);
                            if ($attempts >= 2) {
                                continue;
                            }

                            $total += 1;
                        }
                    }
                });

            $run->total_essays = $total;
            $run->processed_essays = 0;
            $run->skipped_essays = 0;
            $run->save();

            // Chunk to avoid loading huge result sets into memory.
            ExamSubmission::query()
                ->where('exam_id', $exam->id)
                ->with(['user:id,name', 'examPart:id,title'])
                ->orderBy('id')
                ->chunkById(50, function ($submissions) use ($aiService, $run) {
                    $latestRunState = ExamAiFeedbackRun::query()->find($run->id);
                    if (! $latestRunState || $latestRunState->status !== 'running') {
                        // Stop chunk processing immediately if cancelled/failed externally.
                        return false;
                    }

                    /** @var Collection<int, ExamSubmission> $submissions */
                    foreach ($submissions as $submission) {
                        $latestRunState = ExamAiFeedbackRun::query()->find($run->id);
                        if (! $latestRunState || $latestRunState->status !== 'running') {
                            return false;
                        }

                        $answers = is_array($submission->answers)
                            ? $submission->answers
                            : json_decode($submission->answers ?? '[]', true) ?? [];

                        if (! is_array($answers) || empty($answers)) {
                            continue;
                        }

                        $essaysToProcess = [];
                        $essayTargets = [];
                        foreach ($answers as $i => $answer) {
                            if (! is_array($answer)) {
                                continue;
                            }
                            if (! self::isEssayCandidate($answer) || ! self::needsAiCompletion($answer)) {
                                continue;
                            }
                            $attempts = (int) ($answer['ai_feedback_attempts'] ?? 0);
                            if ($attempts >= 2) {
                                continue;
                            }

                            $questionNumber = (int) ($answer['question_number'] ?? ($i + 1));
                            $hasExistingScore = array_key_exists('ai_score', $answer);
                            $essayTargets[$questionNumber] = $i;
                            $essaysToProcess[$questionNumber] = [
                                'essayText' => (string) $answer['answer'],
                                'questionText' => (string) ($answer['question_text'] ?? ''),
                                'maxPoints' => (int) ($answer['points'] ?? 1),
                                // When score already exists, ask AI for feedback only (faster).
                                'feedbackOnly' => $hasExistingScore,
                                'includeFeedback' => true,
                            ];
                        }

                        if (empty($essaysToProcess)) {
                            continue;
                        }

                        $run->forceFill([
                            'current_user_name' => $submission->user?->name,
                            'current_part_title' => $submission->examPart?->title,
                        ])->save();

                        $assessments = $aiService->batchAssessEssays($essaysToProcess);

                        $delta = 0.0;
                        $processedInSubmission = 0;
                        $skippedInSubmission = 0;
                        foreach ($essayTargets as $qNum => $idx) {
                            $answer = $answers[$idx] ?? null;
                            if (! is_array($answer)) {
                                continue;
                            }

                            $assessment = $assessments[$qNum] ?? null;
                            $existingScore = array_key_exists('ai_score', $answer) ? (float) ($answer['ai_score'] ?? 0.0) : null;
                            $newScore = $assessment ? (float) ($assessment['score'] ?? 0.0) : 0.0;
                            $newFeedback = $assessment ? trim((string) ($assessment['feedback'] ?? '')) : '';

                            if ($assessment) {
                                if ($newFeedback !== '') {
                                    $answers[$idx]['ai_feedback'] = $newFeedback;
                                }
                                $answers[$idx]['ai_feedback_attempts'] = (int) ($answer['ai_feedback_attempts'] ?? 0);

                                // Only set score if it didn't exist yet (avoid double-counting in totals).
                                if ($existingScore === null) {
                                    $answers[$idx]['ai_score'] = $newScore;
                                    $delta += $newScore;
                                }

                                $processedInSubmission += 1;

                                continue;
                            }

                            // Failed/empty feedback attempt: bump attempts and auto-skip after 2 tries.
                            $attempts = (int) ($answer['ai_feedback_attempts'] ?? 0) + 1;
                            $answers[$idx]['ai_feedback_attempts'] = $attempts;

                            if ($attempts >= 2) {
                                if ($existingScore === null) {
                                    $answers[$idx]['ai_score'] = 0.0;
                                }
                                $answers[$idx]['ai_feedback'] = 'AI feedback is unavailable after multiple attempts. Please ask your teacher for manual review.';
                                $answers[$idx]['ai_feedback_source'] = 'auto_skip';
                                $skippedInSubmission += 1;
                            }
                        }

                        $submission->answers = json_encode($answers);
                        if (abs($delta) > 0) {
                            // Keep existing objective score and add AI essay score.
                            $submission->score = round(((float) ($submission->score ?? 0)) + $delta, 2);
                        }
                        $submission->status = self::hasPendingEssayWork($answers) ? 'pending_ai' : 'pending_review';
                        $submission->save();

                        if ($processedInSubmission > 0) {
                            $run->processed_essays = (int) $run->processed_essays + $processedInSubmission;
                        }
                        if ($skippedInSubmission > 0) {
                            $run->skipped_essays = (int) ($run->skipped_essays ?? 0) + $skippedInSubmission;
                        }
                        if ($processedInSubmission > 0 || $skippedInSubmission > 0) {
                            $run->save();
                        }
                    }
                });

            $run->refresh();
            if ($run->status === 'cancelled') {
                $run->current_user_name = null;
                $run->current_part_title = null;
                $run->finished_at = $run->finished_at ?? now();
                $run->save();

                return;
            }

            $run->status = 'finished';
            $run->finished_at = now();
            $run->current_user_name = null;
            $run->current_part_title = null;
            $run->save();
        } catch (\Throwable $e) {
            $run->status = 'failed';
            $run->last_error = $e->getMessage();
            $run->finished_at = now();
            $run->save();

            throw $e;
        }
    }

    private static function isEssayCandidate(array $answer): bool
    {
        if (($answer['question_type'] ?? null) !== 'essay') {
            return false;
        }

        $text = $answer['answer'] ?? null;
        if ($text === null || (is_string($text) && trim($text) === '')) {
            return false;
        }

        return true;
    }

    private static function needsAiCompletion(array $answer): bool
    {
        $hasScore = array_key_exists('ai_score', $answer);
        $hasFeedback = array_key_exists('ai_feedback', $answer) && trim((string) ($answer['ai_feedback'] ?? '')) !== '';

        return ! ($hasScore && $hasFeedback);
    }

    private static function hasPendingEssayWork(array $answers): bool
    {
        foreach ($answers as $answer) {
            if (! is_array($answer) || ! self::isEssayCandidate($answer)) {
                continue;
            }

            if (! self::needsAiCompletion($answer)) {
                continue;
            }

            $attempts = (int) ($answer['ai_feedback_attempts'] ?? 0);
            if ($attempts < 2) {
                return true;
            }
        }

        return false;
    }
}
