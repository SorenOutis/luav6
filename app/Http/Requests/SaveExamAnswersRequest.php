<?php

namespace App\Http\Requests;

use App\Enums\QuestionType;
use App\Models\ExamPart;
use App\Support\MatchingAnswerMatcher;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveExamAnswersRequest extends FormRequest
{
    private const MAX_TEXT_LENGTH = 50000;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1', 'max:200'],
            'answers.*.question_number' => ['required', 'integer', 'min:1', 'distinct'],
            'answers.*.answer' => [
                'present',
                'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $values = is_array($value) ? $value : [$value];

                    foreach ($values as $item) {
                        if ($item !== null && ! is_scalar($item)) {
                            $fail('The answer must be text or a selected option.');

                            return;
                        }

                        if (is_string($item) && mb_strlen($item) > self::MAX_TEXT_LENGTH) {
                            $fail('The answer is too long to save.');

                            return;
                        }
                    }
                },
            ],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $examPart = $this->route('examPart');

            if (! $examPart instanceof ExamPart) {
                return;
            }

            $questions = is_array($examPart->questions) ? $examPart->questions : [];
            $submittedAnswers = $this->input('answers', []);

            if (! is_array($submittedAnswers)) {
                return;
            }

            foreach ($submittedAnswers as $index => $submittedAnswer) {
                if (! is_array($submittedAnswer)) {
                    continue;
                }

                $questionNumber = (int) ($submittedAnswer['question_number'] ?? 0);
                $question = $questions[$questionNumber - 1] ?? null;

                if (! is_array($question)) {
                    $validator->errors()->add(
                        "answers.{$index}.question_number",
                        'The selected question does not exist in this exam part.',
                    );

                    continue;
                }

                $answer = $submittedAnswer['answer'] ?? null;
                if ($answer === null || $answer === '') {
                    continue;
                }

                $type = QuestionType::tryFromStored($question['type'] ?? null);
                if ($type?->usesMatchingAnswer()) {
                    $matchingAnswers = is_array($answer) ? $answer : null;
                    $expectedItems = is_array($question['matching_items'] ?? null)
                        ? $question['matching_items']
                        : [];

                    if ($matchingAnswers === null || count($matchingAnswers) > count($expectedItems)) {
                        $validator->errors()->add(
                            "answers.{$index}.answer",
                            'Matching answers must match the available prompts.',
                        );
                    } elseif (collect($matchingAnswers)->contains(fn ($item): bool => $item !== null && ! is_string($item))) {
                        $validator->errors()->add(
                            "answers.{$index}.answer",
                            'Each matching answer must be a text choice.',
                        );
                    } elseif (collect($matchingAnswers)->contains(fn ($item): bool => ! MatchingAnswerMatcher::isValidSelection($item, $question))) {
                        $validator->errors()->add(
                            "answers.{$index}.answer",
                            'Each matching answer must be one of the available choices.',
                        );
                    }
                } elseif ($type?->usesEnumerationAnswer()) {
                    $enumerationAnswers = is_array($answer) ? $answer : null;
                    $expectedItems = is_array($question['enumeration_items'] ?? null)
                        ? $question['enumeration_items']
                        : [];

                    if ($enumerationAnswers === null || count($enumerationAnswers) > count($expectedItems)) {
                        $validator->errors()->add(
                            "answers.{$index}.answer",
                            'Enumeration answers must match the available answer fields.',
                        );
                    } elseif (collect($enumerationAnswers)->contains(fn ($item): bool => $item !== null && ! is_string($item))) {
                        $validator->errors()->add(
                            "answers.{$index}.answer",
                            'Each enumeration answer must be text.',
                        );
                    }
                } elseif ($type?->usesChoiceAnswer()) {
                    $optionCount = count($question['options'] ?? []);
                    if (! is_int($answer) || $answer < 0 || $answer >= $optionCount) {
                        $validator->errors()->add(
                            "answers.{$index}.answer",
                            'The selected option is not valid for this question.',
                        );
                    }
                } elseif ($type?->usesTextAnswer() && ! is_string($answer)) {
                    $validator->errors()->add(
                        "answers.{$index}.answer",
                        'This question requires a text answer.',
                    );
                }
            }
        }];
    }
}
