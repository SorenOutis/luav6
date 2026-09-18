<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamAnswerDraft;
use App\Models\ExamPart;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ExamAnswerDraftService
{
    /**
     * Merge the changed answers into the student's durable per-part draft.
     *
     * @param  list<array{question_number: int, answer: mixed}>  $changedAnswers
     */
    public function save(User $user, Exam $exam, ExamPart $examPart, array $changedAnswers): ExamAnswerDraft
    {
        return DB::transaction(function () use ($user, $exam, $examPart, $changedAnswers): ExamAnswerDraft {
            $now = now();
            $draftKey = [
                'user_id' => $user->id,
                'exam_id' => $exam->id,
                'exam_part_id' => $examPart->id,
            ];

            // Seed the uniquely keyed row without an absent-row race when two
            // browser tabs save their first answer at the same time.
            ExamAnswerDraft::query()->upsert(
                [[
                    ...$draftKey,
                    'answers' => '[]',
                    'saved_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['user_id', 'exam_id', 'exam_part_id'],
                ['updated_at'],
            );

            $draft = ExamAnswerDraft::query()
                ->where($draftKey)
                ->lockForUpdate()
                ->firstOrFail();

            $answers = collect($draft->answers ?? [])->keyBy('question_number');

            foreach ($changedAnswers as $changedAnswer) {
                $questionNumber = (int) $changedAnswer['question_number'];
                $answers->put($questionNumber, [
                    'question_number' => $questionNumber,
                    'answer' => $changedAnswer['answer'],
                ]);
            }

            $draft->forceFill([
                'answers' => $answers->sortKeys()->values()->all(),
                'saved_at' => now(),
            ])->save();

            return $draft;
        }, 3);
    }

    public function answeredCount(ExamAnswerDraft $draft): int
    {
        return collect($draft->answers ?? [])->filter(function (mixed $answer): bool {
            if (! is_array($answer)) {
                return false;
            }

            $value = $answer['answer'] ?? null;

            if (is_array($value)) {
                return count($value) > 0
                    && collect($value)->every(fn ($item): bool => is_string($item) && trim($item) !== '');
            }

            return $value !== null && (! is_string($value) || trim($value) !== '');
        })->count();
    }
}
