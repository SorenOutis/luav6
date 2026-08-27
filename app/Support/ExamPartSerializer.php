<?php

namespace App\Support;

use App\Enums\QuestionType;
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
     *                                  never answered) the question text/options are omitted entirely so
     *                                  the payload carries nothing for them to review.
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
            $type = QuestionType::tryFromStored($question['type'] ?? null) ?? QuestionType::MultipleChoice;
            $enumerationItems = $type->usesEnumerationAnswer()
                ? self::enumerationItems($question)
                : null;
            $matchingItems = $type->usesMatchingAnswer()
                ? self::matchingItems($question, $revealAnswers)
                : null;
            $safe = [
                'text' => $question['text'] ?? '',
                'type' => $type->value,
                'type_label' => $type->label(),
                'points' => $enumerationItems !== null
                    ? array_sum(array_column($enumerationItems, 'points'))
                    : ($matchingItems !== null
                        ? array_sum(array_column($matchingItems, 'points'))
                        : $question['points'] ?? null),
                'enumeration_items' => $enumerationItems,
                'matching_items' => $matchingItems,
                'matching_options' => $matchingItems !== null ? self::matchingOptions($question) : null,
                'options' => self::options($question, $revealAnswers),
            ];

            // Only ever emit the key when review is permitted. Note the key is
            // omitted entirely rather than nulled, so a `correct_answer` of null
            // can't be mistaken for "no answer recorded".
            if ($revealAnswers && array_key_exists('correct_answer', $question)) {
                $safe['correct_answer'] = $question['correct_answer'];

                if ($type === QuestionType::Identification) {
                    $safe['accepted_answers'] = IdentificationAnswerMatcher::acceptedAnswers($question);
                }
            }

            return $safe;
        })->values()->all();
    }

    /**
     * Expose Enumeration item counts and points without leaking expected answers.
     *
     * @param  array<string, mixed>  $question
     * @return array<int, array{points: float}>
     */
    private static function enumerationItems(array $question): array
    {
        if (! isset($question['enumeration_items']) || ! is_array($question['enumeration_items'])) {
            return [];
        }

        return collect($question['enumeration_items'])
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'points' => (float) ($item['points'] ?? 1),
            ])
            ->values()
            ->all();
    }

    /**
     * Expose Matching Type prompts and points, keeping the expected match hidden
     * until answer review is permitted.
     *
     * @param  array<string, mixed>  $question
     * @return array<int, array<string, mixed>>
     */
    private static function matchingItems(array $question, bool $revealAnswers): array
    {
        return collect($question['matching_items'] ?? [])
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item, int $index) use ($revealAnswers): array {
                $safe = [
                    'index' => $index,
                    'prompt' => (string) ($item['prompt'] ?? ''),
                    'points' => (float) ($item['points'] ?? 1),
                ];

                if ($revealAnswers) {
                    $safe['answer'] = (string) ($item['answer'] ?? '');
                }

                return $safe;
            })
            ->filter(fn (array $item): bool => $item['prompt'] !== '')
            ->values()
            ->all();
    }

    /**
     * Provide a stable, reordered list of visible right-column choices. The
     * selected value is the visible answer text, not a hidden correct index.
     *
     * @param  array<string, mixed>  $question
     * @return array<int, array{value: string, text: string}>
     */
    private static function matchingOptions(array $question): array
    {
        return collect($question['matching_items'] ?? [])
            ->filter(fn ($item): bool => is_array($item) && trim((string) ($item['answer'] ?? '')) !== '')
            ->map(fn (array $item): string => trim((string) $item['answer']))
            ->unique()
            ->sortBy(fn (string $answer): string => hash('sha256', (string) ($question['text'] ?? '').'|'.$answer))
            ->values()
            ->map(fn (string $answer): array => ['value' => $answer, 'text' => $answer])
            ->all();
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
