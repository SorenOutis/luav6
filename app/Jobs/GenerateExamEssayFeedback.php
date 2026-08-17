<?php

namespace App\Jobs;

use App\Enums\EssayGradingMethod;
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

/** Re-run missing automatic essay grades and feedback for an entire exam. */
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
            $total = 0;
            ExamSubmission::query()
                ->where('exam_id', $exam->id)
                ->select(['id', 'answers'])
                ->orderBy('id')
                ->chunkById(200, function ($submissions) use (&$total): void {
                    /** @var Collection<int, ExamSubmission> $submissions */
                    foreach ($submissions as $submission) {
                        $total += $this->candidateCount($submission->answers ?? []);
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
                ->chunkById(50, function ($submissions) use ($aiService, $run) {
                    foreach ($submissions as $submission) {
                        $latest = ExamAiFeedbackRun::query()->find($run->id);
                        if (! $latest || $latest->status !== 'running') {
                            return false;
                        }

                        $latest->forceFill([
                            'current_user_name' => $submission->user?->name,
                            'current_part_title' => $submission->examPart?->title,
                        ])->save();

                        $before = $this->candidateCount($submission->answers ?? []);

                        (new GradeExamSubmissionEssays($submission->id))->handle($aiService);

                        $submission->refresh();
                        $after = $this->candidateCount($submission->answers ?? []);
                        $processed = max(0, $before - $after);

                        if ($processed > 0) {
                            $latest->increment('processed_essays', $processed);
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

    /** @param array<int, mixed> $answers */
    private function candidateCount(array $answers): int
    {
        return collect($answers)
            ->filter(fn ($answer): bool => is_array($answer)
                && ($answer['question_type'] ?? null) === 'essay'
                && EssayGradingMethod::forAnswer($answer) === EssayGradingMethod::Ai
                && trim((string) ($answer['answer'] ?? '')) !== ''
                && ! (
                    array_key_exists('ai_score', $answer)
                    && trim((string) ($answer['ai_feedback'] ?? '')) !== ''
                ))
            ->count();
    }
}
