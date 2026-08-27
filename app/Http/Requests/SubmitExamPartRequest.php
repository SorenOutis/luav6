<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

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

    public function messages(): array
    {
        return [
            'answers.required' => 'No answers were received. Please try submitting again.',
            'answers.max' => 'This submission contains too many answers.',
        ];
    }
}
