<?php

namespace App\Support;

final class MatchingAnswerMatcher
{
    /**
     * @param  array<string, mixed>  $question
     * @return array<int, array{prompt: string, answer: string, points: float}>
     */
    public static function items(array $question): array
    {
        return collect($question['matching_items'] ?? [])
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'prompt' => trim((string) ($item['prompt'] ?? '')),
                'answer' => trim((string) ($item['answer'] ?? '')),
                'points' => (float) ($item['points'] ?? 1),
            ])
            ->filter(fn (array $item): bool => $item['prompt'] !== '' && $item['answer'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<int, string>
     */
    public static function options(array $question): array
    {
        return collect(self::items($question))
            ->pluck('answer')
            ->unique(fn (string $answer): string => self::normalize($answer))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $question
     */
    public static function maxPoints(array $question): float
    {
        return array_sum(array_column(self::items($question), 'points'));
    }

    /**
     * @param  array<string, mixed>  $question
     */
    public static function score(array $question, mixed $submittedAnswer): float
    {
        return array_sum(array_column(self::breakdown($question, $submittedAnswer), 'earned'));
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<int, array{prompt: string, expected: string, submitted: string, points: float, earned: float, matched: bool}>
     */
    public static function breakdown(array $question, mixed $submittedAnswer): array
    {
        $submitted = is_array($submittedAnswer) ? $submittedAnswer : [];
        $usedAnswers = [];

        return collect(self::items($question))
            ->map(function (array $item, int $index) use ($submitted, &$usedAnswers): array {
                $answer = trim((string) ($submitted[$index] ?? ''));
                $normalized = self::normalize($answer);
                $expected = self::normalize($item['answer']);
                $matched = $normalized !== ''
                    && $normalized === $expected
                    && ! isset($usedAnswers[$expected]);

                if ($matched) {
                    $usedAnswers[$expected] = true;
                }

                return [
                    'prompt' => $item['prompt'],
                    'expected' => $item['answer'],
                    'submitted' => $answer,
                    'points' => $item['points'],
                    'earned' => $matched ? $item['points'] : 0.0,
                    'matched' => $matched,
                ];
            })
            ->values()
            ->all();
    }

    public static function isValidSelection(mixed $answer, array $question): bool
    {
        if (! is_string($answer) || trim($answer) === '') {
            return true;
        }

        $normalized = self::normalize($answer);

        return collect(self::options($question))
            ->contains(fn (string $option): bool => self::normalize($option) === $normalized);
    }

    private static function normalize(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        $text = preg_replace('/[^\w\s]/u', '', $text) ?? '';

        return trim($text);
    }
}
