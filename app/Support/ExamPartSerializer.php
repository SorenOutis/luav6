<?php

namespace App\Support;

use App\Models\Exam;
use App\Models\ExamPart;
use Illuminate\Support\Collection;

/**
 * Serializes exam parts for the student-facing frontend.
 *
 * ⚠️ SECURITY: `ExamPart::$questions` holds the answer key (`options[].is_correct`
 * and `correct_answer`). Passing the raw model to Inertia publishes that key in
 * the page props, where any student can read it from DevTools.
 *
 * Answers are revealed ONLY when the exam is closed AND the student actually
 * has at least one submission (product decision: review after close, not after
 * submit — otherwise students in the same room can share answers while others
 * are still working — and never for a student who did not take the exam).
 */
class ExamPartSerializer
{
    /**
     * Serialize a collection of parts.
     *
     * @param  Collection<int, ExamPart>|iterable<ExamPart>  $parts
     * @param  bool  $includeQuestions  When false (a closed exam the student
     *     never answered) the question text/options are omitted entirely so
     *     the payload carries nothing for them to review.
     * @return array<int, array<string, mixed>>
     */
    public static function many(iterable $parts, bool $revealAnswers, bool $includeQuestions = true): array
    {
        return collect($parts)
            ->map(fn (ExamPart $part) => self::one($part, $revealAnswers, $includeQuestions))
            ->values()
            ->all();
    }

    /**
     * Serialize a single part.
     *
     * @return array<string, mixed>
     */
    public static function one(ExamPart $part, bool $revealAnswers, bool $includeQuestions = true): array
    {
        return [
            'id' => $part->id,
            'exam_id' => $part->exam_id,
            'title' => $part->title,
            'instructions' => $part->instructions,
            'type' => $part->type,
            'sort_order' => $part->sort_order,
            'points' => $part->points,
            'questions' => $includeQuestions
                ? self::questions($part, $revealAnswers)
                : [],
        ];
    }

    /**
     * Whether the answer key may be shown for this exam.
     *
     * Admins always see it. Students only once the exam is closed AND they
     * have at least one submission — a student who never answered a closed
     * exam must not be able to open "review results" and read the questions
     * (or the key) after the fact.
     */
    public static function mayRevealAnswers(
        Exam $exam,
        ?bool $isAdmin = null,
        bool $hasSubmitted = false
    ): bool {
        $isAdmin ??= (bool) auth()->user()?->is_admin;

        return $isAdmin || ($exam->status === 'closed' && $hasSubmitted);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function questions(ExamPart $part, bool $revealAnswers): array
    {
        $questions = is_array($part->questions) ? $part->questions : [];

        return collect($questions)->map(function ($question) use ($revealAnswers) {
            $safe = [
                'text' => $question['text'] ?? '',
                'type' => $question['type'] ?? 'multiple_choice',
                'points' => $question['points'] ?? null,
                'options' => self::options($question, $revealAnswers),
            ];

            // Only ever emit the key when review is permitted. Note the key is
            // omitted entirely rather than nulled, so a `correct_answer` of null
            // can't be mistaken for "no answer recorded".
            if ($revealAnswers && array_key_exists('correct_answer', $question)) {
                $safe['correct_answer'] = $question['correct_answer'];
            }

            return $safe;
        })->values()->all();
    }

    /**
     * Option text is always required to render the question; `is_correct` is not.
     *
     * @param  array<string, mixed>  $question
     * @return array<int, array<string, mixed>>|null
     */
    private static function options(array $question, bool $revealAnswers): ?array
    {
        if (! isset($question['options']) || ! is_array($question['options'])) {
            return null;
        }

        return collect($question['options'])->map(function ($option) use ($revealAnswers) {
            $safe = ['text' => $option['text'] ?? ''];

            if ($revealAnswers) {
                $safe['is_correct'] = (bool) ($option['is_correct'] ?? false);
            }

            return $safe;
        })->values()->all();
    }
}
