<?php

namespace App\Support;

final class IdentificationAnswerMatcher
{
    /**
     * @param  array<string, mixed>  $question
     * @return array<int, string>
     */
    public static function acceptedAnswers(array $question): array
    {
        $answers = [];
        $primary = $question['correct_answer'] ?? null;

        if (is_string($primary) && trim($primary) !== '') {
            $answers[] = trim($primary);
        }

        foreach ((array) ($question['accepted_answers'] ?? []) as $accepted) {
            $value = is_array($accepted) ? ($accepted['answer'] ?? null) : $accepted;

            if (is_string($value) && trim($value) !== '') {
                $answers[] = trim($value);
            }
        }

        return collect($answers)
            ->unique(fn (string $answer): string => self::normalize($answer))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $question
     */
    public static function matches(mixed $submittedAnswer, array $question): bool
    {
        $normalizedSubmitted = self::normalize((string) $submittedAnswer);

        if ($normalizedSubmitted === '') {
            return false;
        }

        return collect(self::acceptedAnswers($question))
            ->contains(fn (string $answer): bool => self::normalize($answer) === $normalizedSubmitted);
    }

    /**
     * @param  array<string, mixed>  $question
     */
    public static function display(array $question): string
    {
        $answers = self::acceptedAnswers($question);

        return $answers === [] ? 'No answer key set' : implode(' or ', $answers);
    }

    private static function normalize(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        $text = preg_replace('/[^\w\s]/u', '', $text) ?? '';

        return trim($text);
    }
}
