<?php

namespace App\Http\Requests;

use App\Models\ExamPart;
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
                    if (! is_scalar($value)) {
                        $fail('The answer must be text or a selected option.');

                        return;
                    }

                    if (is_string($value) && mb_strlen($value) > self::MAX_TEXT_LENGTH) {
                        $fail('The answer is too long to save.');
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

                $type = $question['type'] ?? null;
                if (in_array($type, ['multiple_choice', 'true_false'], true)) {
                    $optionCount = count($question['options'] ?? []);
                    if (! is_int($answer) || $answer < 0 || $answer >= $optionCount) {
                        $validator->errors()->add(
                            "answers.{$index}.answer",
                            'The selected option is not valid for this question.',
                        );
                    }
                } elseif (in_array($type, ['identification', 'essay'], true) && ! is_string($answer)) {
                    $validator->errors()->add(
                        "answers.{$index}.answer",
                        'This question requires a text answer.',
                    );
                }
            }
        }];
    }
}
