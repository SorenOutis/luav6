<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\Utf8;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AiQuestionDraft extends Model
{
    use BelongsToWorkspace;

    public bool $skipAutomaticReviewAudit = false;

    /**
     * Maximum characters of source material kept on the record.
     *
     * The generator only ever reads the first ~12k characters, and the full
     * text is loaded into Livewire state by the edit form — keeping this
     * bounded prevents multi-megabyte payloads on every request.
     */
    public const MAX_SOURCE_TEXT_LENGTH = 100_000;

    public const REVIEW_NOT_READY = 'not_ready';

    public const REVIEW_AWAITING = 'awaiting_review';

    public const REVIEW_APPROVED = 'approved';

    public const REVIEW_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'target_exam_id',
        'title',
        'source_filename',
        'source_text',
        'topic',
        'type_counts',
        'difficulty',
        'attachment_instructions',
        'provider',
        'status',
        'questions',
        'last_error',
        'ai_response',
        'generated_at',
        'review_status',
        'review_version',
        'submitted_for_review_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'workspace_id',
        'admin_id',
    ];

    protected $casts = [
        'type_counts' => 'array',
        'questions' => 'array',
        'review_version' => 'integer',
        'generated_at' => 'datetime',
        'submitted_for_review_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Extracted PDF/DOCX text and AI output can contain malformed UTF-8,
        // which makes Livewire's json_encode() of the form state explode with
        // "Malformed UTF-8 characters, possibly incorrectly encoded".
        // Sanitize on every save so all write paths (jobs, Filament, tinker)
        // store clean data — this also repairs legacy rows when re-saved.
        static::saving(function (AiQuestionDraft $draft): void {
            foreach (['title', 'topic', 'source_filename', 'source_text', 'last_error', 'ai_response', 'rejection_reason', 'attachment_instructions'] as $attribute) {
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

            // Approval applies to an exact reviewed question set. Editing an
            // approved set automatically returns it to the review queue.
            if ($draft->exists && $draft->isDirty('questions')) {
                if (
                    $draft->getOriginal('review_status') === self::REVIEW_APPROVED
                    && $draft->review_status === self::REVIEW_APPROVED
                ) {
                    $draft->review_status = self::REVIEW_AWAITING;
                    $draft->review_version = (int) $draft->getOriginal('review_version') + 1;
                    $draft->submitted_for_review_at = now();
                    $draft->reviewed_by = null;
                    $draft->reviewed_at = null;
                    $draft->rejection_reason = null;
                } elseif (
                    $draft->getOriginal('review_status') === self::REVIEW_AWAITING
                    && $draft->review_status === self::REVIEW_AWAITING
                    && ! $draft->isDirty('review_version')
                ) {
                    $draft->review_version = (int) $draft->getOriginal('review_version') + 1;
                    $draft->submitted_for_review_at = now();
                }
            }
        });

        static::updated(function (AiQuestionDraft $draft): void {
            if ($draft->skipAutomaticReviewAudit) {
                return;
            }

            if (! $draft->wasChanged('questions') || $draft->review_status !== self::REVIEW_AWAITING) {
                return;
            }

            $previousReviewStatus = $draft->getOriginal('review_status');
            if (! in_array($previousReviewStatus, [self::REVIEW_APPROVED, self::REVIEW_AWAITING], true)) {
                return;
            }

            $before = $draft->getOriginal('questions');
            if (is_string($before)) {
                $before = json_decode($before, true);
            }

            $draft->reviewEvents()->create([
                'workspace_id' => $draft->workspace_id,
                'actor_id' => auth()->id(),
                'event' => $previousReviewStatus === self::REVIEW_APPROVED
                    ? 'approval_revoked_by_edit'
                    : 'teacher_revised_questions',
                'version' => $draft->review_version,
                'before_payload' => ['questions' => is_array($before) ? $before : []],
                'after_payload' => ['questions' => $draft->questions ?? []],
            ]);
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

    public function targetExam(): BelongsTo
    {
        return $this->belongsTo(Exam::class, 'target_exam_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function reviewEvents(): MorphMany
    {
        return $this->morphMany(AiReviewEvent::class, 'reviewable')->latest('id');
    }
}
