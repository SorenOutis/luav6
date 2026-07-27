<?php

namespace App\Enums;

/**
 * Phase 4.1 — exam submission lifecycle.
 *
 * submitted      auto-gradable only; final
 * pending_review contains essays; AI has scored, teacher feedback pending
 * pending_ai     queued for the manual AI feedback pass
 * graded         teacher-triggered feedback pass complete
 */
enum SubmissionStatus: string
{
    case Submitted = 'submitted';
    case PendingReview = 'pending_review';
    case PendingAi = 'pending_ai';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::PendingReview => 'Pending Review',
            self::PendingAi => 'Pending AI',
            self::Graded => 'Graded',
        };
    }

    /** Nothing further will change the score automatically. */
    public function isFinal(): bool
    {
        return $this === self::Submitted || $this === self::Graded;
    }

    public function needsTeacherAttention(): bool
    {
        return $this === self::PendingReview || $this === self::PendingAi;
    }
}
