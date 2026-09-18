<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Ensures exam submission answers are always an array for Filament repeaters and JSON in the database.
 */
class ExamSubmissionAnswersCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values($decoded);
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $value;
            }

            return json_encode([], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        if (! is_array($value)) {
            // A failed json_encode() upstream used to deliver `false` here,
            // which crashed array_values() and took the whole submission down
            // with it — the student's answers were never recorded. Never throw
            // from a cast; degrade to an empty list instead.
            return json_encode([], JSON_INVALID_UTF8_SUBSTITUTE);
        }

        return json_encode(
            array_values($value),
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE
        );
    }
}
