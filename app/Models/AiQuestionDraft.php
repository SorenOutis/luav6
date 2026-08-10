<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\Utf8;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiQuestionDraft extends Model
{
    use BelongsToWorkspace;

    /**
     * Maximum characters of source material kept on the record.
     *
     * The generator only ever reads the first ~12k characters, and the full
     * text is loaded into Livewire state by the edit form — keeping this
     * bounded prevents multi-megabyte payloads on every request.
     */
    public const MAX_SOURCE_TEXT_LENGTH = 100_000;

    protected $fillable = [
        'user_id',
        'title',
        'source_filename',
        'source_text',
        'topic',
        'type_counts',
        'difficulty',
        'provider',
        'status',
        'questions',
        'last_error',
        'ai_response',
        'generated_at',
        'admin_id',
    ];

    protected $casts = [
        'type_counts' => 'array',
        'questions' => 'array',
        'generated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Extracted PDF/DOCX text and AI output can contain malformed UTF-8,
        // which makes Livewire's json_encode() of the form state explode with
        // "Malformed UTF-8 characters, possibly incorrectly encoded".
        // Sanitize on every save so all write paths (jobs, Filament, tinker)
        // store clean data — this also repairs legacy rows when re-saved.
        static::saving(function (AiQuestionDraft $draft): void {
            foreach (['title', 'topic', 'source_filename', 'source_text', 'last_error', 'ai_response'] as $attribute) {
                $value = $draft->getAttribute($attribute);

                if (is_string($value)) {
                    $draft->setAttribute($attribute, Utf8::clean($value));
                }
            }

            $sourceText = $draft->getAttribute('source_text');

            if (is_string($sourceText) && mb_strlen($sourceText) > self::MAX_SOURCE_TEXT_LENGTH) {
                $draft->setAttribute('source_text', mb_substr($sourceText, 0, self::MAX_SOURCE_TEXT_LENGTH));
            }

            $questions = $draft->getAttribute('questions');

            if (is_array($questions)) {
                $draft->setAttribute('questions', Utf8::cleanDeep($questions));
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Read-path protection
    |--------------------------------------------------------------------------
    |
    | The accessors below guarantee that values READ from the database are
    | valid UTF-8 as well. This matters for legacy rows (or rows written by
    | raw queries/imports) that predate the saving hook: without it, a single
    | malformed byte in source_text makes Livewire's json_encode() of the
    | edit-form state throw "Malformed UTF-8 characters" and 500 the page.
    |
    */

    protected function sourceText(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : Utf8::clean($value),
        );
    }

    protected function lastError(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : Utf8::clean($value),
        );
    }

    protected function aiResponse(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : Utf8::clean($value),
        );
    }

    protected function title(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : Utf8::clean($value),
        );
    }

    protected function topic(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : Utf8::clean($value),
        );
    }

    protected function sourceFilename(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null ? null : Utf8::clean($value),
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
