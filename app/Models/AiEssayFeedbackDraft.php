<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\Utf8;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class AiEssayFeedbackDraft extends Model
{
    use BelongsToWorkspace;

    public const STATUS_GENERATING = 'generating';

    public const STATUS_AWAITING_REVIEW = 'awaiting_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_SUPERSEDED = 'superseded';

    protected $fillable = [
        'workspace_id',
        'exam_submission_id',
        'question_number',
        'question_text',
        'answer_text',
        'max_points',
        'proposed_score',
        'proposed_feedback',
        'provider',
        'model',
        'source_hash',
        'review_status',
        'generation_version',
        'generated_at',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'last_error',
    ];

    protected static function booted(): void
    {
        static::creating(function (AiEssayFeedbackDraft $draft): void {
            $draft->public_id ??= (string) Str::uuid7();
        });

        static::saving(function (AiEssayFeedbackDraft $draft): void {
            foreach (['question_text', 'answer_text', 'proposed_feedback', 'rejection_reason', 'last_error'] as $attribute) {
                $value = $draft->getAttribute($attribute);
                if (is_string($value)) {
                    $draft->setAttribute($attribute, Utf8::clean($value));
                }
            }

            $draft->proposed_score = max(0, min(
                (float) ($draft->max_points ?? 0),
                (float) ($draft->proposed_score ?? 0),
            ));
        });
    }

    protected function casts(): array
    {
        return [
            'question_number' => 'integer',
            'max_points' => 'decimal:2',
            'proposed_score' => 'decimal:2',
            'generation_version' => 'integer',
            'generated_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ExamSubmission::class, 'exam_submission_id');
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
