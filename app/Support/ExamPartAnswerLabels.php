<?php

namespace App\Support;

use App\Models\ExamPart;

class ExamPartAnswerLabels
{
    /**
     * @param  callable(string): mixed  $get
     * @return array<string, string>
     */
    public static function choiceOptionsForRow(callable $get): array
    {
        $partId = $get('../../exam_part_id');
        $questionNumber = (int) ($get('question_number') ?? 0);

        if (! $partId || $questionNumber < 1) {
            return [];
        }

        $part = ExamPart::query()->find($partId);
        if (! $part) {
            return [];
        }

        $questions = is_array($part->questions) ? $part->questions : [];
        $question = $questions[$questionNumber - 1] ?? null;
        if (! is_array($question)) {
            return [];
        }

        $options = [];
        foreach ($question['options'] ?? [] as $idx => $option) {
            $label = is_array($option)
                ? (string) ($option['text'] ?? 'Option '.((int) $idx + 1))
                : (string) $option;
            $options[(string) $idx] = $label;
        }

        return $options;
    }

    /**
     * Like {@see choiceOptionsForRow}, but keeps a readable row when the stored index no longer matches the exam part.
     *
     * @return array<string, string>
     */
    public static function choiceOptionsForRowWithCurrent(callable $get): array
    {
        $options = self::choiceOptionsForRow($get);
        $current = $get('response_choice');

        if ($current === null || $current === '') {
            return $options;
        }

        $key = (string) (int) $current;

        if (! array_key_exists($key, $options)) {
            $options[$key] = 'Choice #'.(((int) $current) + 1).' (no matching option in exam part)';
        }

        return $options;
    }

    public static function correctChoiceHint(callable $get): ?string
    {
        $partId = $get('../../exam_part_id');
        $questionNumber = (int) ($get('question_number') ?? 0);

        if (! $partId || $questionNumber < 1) {
            return null;
        }

        $part = ExamPart::query()->find($partId);
        if (! $part) {
            return null;
        }

        $questions = is_array($part->questions) ? $part->questions : [];
        $question = $questions[$questionNumber - 1] ?? null;
        if (! is_array($question)) {
            return null;
        }

        foreach ($question['options'] ?? [] as $idx => $option) {
            if (! is_array($option) || empty($option['is_correct'])) {
                continue;
            }

            $label = (string) ($option['text'] ?? 'Option '.((int) $idx + 1));

            return 'Correct answer in exam: '.$label;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function splitAnswerFieldsForForm(array $data): array
    {
        if (empty($data['answers']) || ! is_array($data['answers'])) {
            return $data;
        }

        foreach ($data['answers'] as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = $row['question_type'] ?? '';
            $answer = $row['answer'] ?? null;

            if (in_array($type, ['multiple_choice', 'true_false'], true)) {
                $data['answers'][$key]['response_choice'] = self::normalizeChoiceState($answer);
            } else {
                $data['answers'][$key]['response_text'] = $answer === null || is_scalar($answer)
                    ? (string) $answer
                    : '';
            }

            unset($data['answers'][$key]['answer']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeAnswerFieldsForSave(array $data): array
    {
        if (empty($data['answers']) || ! is_array($data['answers'])) {
            return $data;
        }

        foreach ($data['answers'] as $key => $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = $row['question_type'] ?? '';

            if (in_array($type, ['multiple_choice', 'true_false'], true)) {
                $choice = $row['response_choice'] ?? null;
                $data['answers'][$key]['answer'] = ($choice === null || $choice === '')
                    ? null
                    : (int) $choice;
            } else {
                $text = $row['response_text'] ?? null;
                $data['answers'][$key]['answer'] = ($text === null || $text === '')
                    ? null
                    : (string) $text;
            }

            unset($data['answers'][$key]['response_choice'], $data['answers'][$key]['response_text']);
        }

        return $data;
    }

    private static function normalizeChoiceState(mixed $answer): ?string
    {
        if ($answer === null || $answer === '') {
            return null;
        }

        return (string) (int) $answer;
    }
}
