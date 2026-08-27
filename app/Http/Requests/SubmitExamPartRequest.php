<?php

namespace App\Http\Requests;

use App\Enums\QuestionType;
use App\Models\ExamPart;
use App\Support\MatchingAnswerMatcher;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Phase 1.6 — validate the shape of an exam part submission.
 *
 * Previously only `answers => required|array` was enforced, so malformed
 * payloads reached the grading loop and relied on `?? null` not to crash.
 */
class SubmitExamPartRequest extends FormRequest
{
    private const MAX_TEXT_LENGTH = 50000;

    /**
     * Authorization is handled by ExamController::assertCanSubmit(), which needs
     * both route models and is shared with other actions.
     */
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
            'answers' => ['required', 'array', 'max:200'],
            'answers.*.question_number' => ['required', 'integer', 'min:1'],
            // `present` (not `required`) — a deliberately blank answer is valid
            // and must still be recorded as attempted.
            'answers.*.answer' => [
                'present',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $values = is_array($value) ? $value : [$value];

                    foreach ($values as $item) {
                        if ($item !== null && ! is_scalar($item)) {
                            $fail('Each answer must be text or a selected option.');

                            return;
                        }

                        if (is_string($item) && mb_strlen($item) > self::MAX_TEXT_LENGTH) {
                            $fail('The answer is too long to submit.');

                            return;
                        }
                    }
                },
            ],
        ];
    }

    /**
     * Matching Type answers are submitted as one visible choice per prompt.
     * Blank rows are allowed so the controller can record unanswered prompts.
     *
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
                    continue;
                }

                $type = QuestionType::tryFromStored($question['type'] ?? null);
                if (! $type?->usesMatchingAnswer()) {
                    continue;
                }

                $answer = $submittedAnswer['answer'] ?? null;
                $matchingAnswers = is_array($answer) ? $answer : null;
                $expectedItems = is_array($question['matching_items'] ?? null)
                    ? $question['matching_items']
                    : [];

                if ($matchingAnswers === null || count($matchingAnswers) > count($expectedItems)) {
                    $validator->errors()->add(
                        "answers.{$index}.answer",
                        'Matching answers must match the available prompts.',
                    );

                    continue;
                }

                foreach ($matchingAnswers as $matchingAnswer) {
                    if ($matchingAnswer !== null && ! is_string($matchingAnswer)) {
                        $validator->errors()->add(
                            "answers.{$index}.answer",
                            'Each matching answer must be a text choice.',
                        );

                        break;
                    }

                    if (! MatchingAnswerMatcher::isValidSelection($matchingAnswer, $question)) {
                        $validator->errors()->add(
                            "answers.{$index}.answer",
                            'Each matching answer must be one of the available choices.',
                        );

                        break;
                    }
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'answers.required' => 'No answers were received. Please try submitting again.',
            'answers.max' => 'This submission contains too many answers.',
        ];
    }
}
