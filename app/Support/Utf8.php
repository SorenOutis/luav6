<?php

namespace App\Support;

/**
 * Guarantees strings are valid UTF-8.
 *
 * Text extracted from PDFs/DOCX (Smalot PdfParser, PhpWord) or returned by
 * AI providers frequently contains malformed byte sequences, null bytes and
 * control characters. When such a string reaches json_encode() — e.g. when
 * Livewire serializes component state — PHP throws
 * "Malformed UTF-8 characters, possibly incorrectly encoded".
 *
 * Cleaning at the boundary (and again on model save) keeps every storage
 * and rendering path safe.
 */
class Utf8
{
    /**
     * Return a valid UTF-8 version of the given string.
     *
     * - Invalid byte sequences are replaced (never throws).
     * - Null bytes and C0 control characters are removed
     *   (tab, line feed and carriage return are preserved).
     */
    public static function clean(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            // Same-encoding conversion substitutes malformed byte sequences
            // with the replacement character while leaving valid parts intact.
            $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        // Strip null bytes and disallowed control characters (keep \t, \n, \r).
        // The /u modifier is safe here: the string is valid UTF-8 at this point.
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value);

        return $cleaned ?? '';
    }

    /**
     * Recursively clean every string (values and keys) inside arrays.
     *
     * Non-string scalars and objects are returned untouched.
     */
    public static function cleanDeep(mixed $value): mixed
    {
        if (is_string($value) || $value === null) {
            return self::clean($value);
        }

        if (is_array($value)) {
            $out = [];

            foreach ($value as $key => $item) {
                $out[is_string($key) ? self::clean($key) : $key] = self::cleanDeep($item);
            }

            return $out;
        }

        return $value;
    }

    /**
     * Whether the given string is safe to JSON-encode as-is.
     */
    public static function isValid(?string $value): bool
    {
        return $value === null || mb_check_encoding($value, 'UTF-8');
    }
}
