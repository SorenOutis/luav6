<?php

namespace App\Jobs;

use App\Models\AiEssayFeedbackDraft;
use App\Models\Exam;
use App\Models\ExamAiFeedbackRun;
use App\Models\ExamSubmission;
use App\Services\AIService;
use App\Services\AiReviewService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** Generate teacher-reviewable essay feedback drafts for an entire exam. */
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

    public function handle(AIService $aiService, AiReviewService $reviews): void
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
            $total = 0;
            ExamSubmission::query()
                ->where('exam_id', $exam->id)
                ->select(['id', 'answers'])
                ->orderBy('id')
                ->chunkById(200, function ($submissions) use (&$total): void {
                    /** @var Collection<int, ExamSubmission> $submissions */
                    $draftQuestions = AiEssayFeedbackDraft::query()
                        ->withoutGlobalScope('workspace')
                        ->whereIn('exam_submission_id', $submissions->pluck('id'))
                        ->get(['exam_submission_id', 'question_number'])
                        ->groupBy('exam_submission_id')
                        ->map(fn (Collection $drafts) => $drafts->pluck('question_number')->map(fn ($number) => (int) $number)->all());

                    foreach ($submissions as $submission) {
                        $total += $this->candidateCount(
                            $submission->answers ?? [],
                            $draftQuestions->get($submission->id, []),
                        );
                    }
                });

            $run->forceFill([
                'total_essays' => $total,
                'processed_essays' => 0,
                'skipped_essays' => 0,
            ])->save();

            ExamSubmission::query()
                ->where('exam_id', $exam->id)
                ->with(['user:id,name', 'examPart:id,title'])
                ->orderBy('id')
                ->chunkById(50, function ($submissions) use ($aiService, $reviews, $run) {
                    foreach ($submissions as $submission) {
                        $latest = ExamAiFeedbackRun::query()->find($run->id);
                        if (! $latest || $latest->status !== 'running') {
                            return false;
                        }

                        $latest->forceFill([
                            'current_user_name' => $submission->user?->name,
                            'current_part_title' => $submission->examPart?->title,
                        ])->save();

                        $before = AiEssayFeedbackDraft::query()
                            ->withoutGlobalScope('workspace')
                            ->where('exam_submission_id', $submission->id)
                            ->where('review_status', AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW)
                            ->count();

                        (new GradeExamSubmissionEssays($submission->id))->handle($aiService, $reviews);

                        $after = AiEssayFeedbackDraft::query()
                            ->withoutGlobalScope('workspace')
                            ->where('exam_submission_id', $submission->id)
                            ->where('review_status', AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW)
                            ->count();
                        $created = max(0, $after - $before);

                        if ($created > 0) {
                            $latest->increment('processed_essays', $created);
                        }
                        if ($submission->fresh()->grading_failed) {
                            $latest->increment('skipped_essays');
                        }
                    }

                    return true;
                });

            $run->refresh();
            if ($run->status === 'cancelled') {
                $run->forceFill([
                    'current_user_name' => null,
                    'current_part_title' => null,
                    'finished_at' => $run->finished_at ?? now(),
                ])->save();

                return;
            }

            $run->forceFill([
                'status' => 'finished',
                'finished_at' => now(),
                'current_user_name' => null,
                'current_part_title' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $run->forceFill([
                'status' => 'failed',
                'last_error' => $exception->getMessage(),
                'finished_at' => now(),
            ])->save();

            throw $exception;
        }
    }

    /** @param array<int, int> $existingDraftQuestions */
    private function candidateCount(array $answers, array $existingDraftQuestions): int
    {
        return collect($answers)
            ->filter(fn ($answer): bool => is_array($answer)
                && ($answer['question_type'] ?? null) === 'essay'
                && trim((string) ($answer['answer'] ?? '')) !== ''
                && ! in_array((int) ($answer['question_number'] ?? 0), $existingDraftQuestions, true)
                && ! (
                    array_key_exists('ai_score', $answer)
                    && trim((string) ($answer['ai_feedback'] ?? '')) !== ''
                ))
            ->count();
    }
}
