<?php

namespace App\Enums;

/**
 * Phase 4.1 — exam submission lifecycle.
 *
 * submitted      auto-gradable only; final
 * pending_review contains essays or AI proposals awaiting explicit teacher review
 * pending_ai     AI proposal generation is still pending/failed
 * graded         every essay score/feedback decision has been applied by a teacher
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
