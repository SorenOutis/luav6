<?php

namespace App\Enums;

/**
 * Phase 4.1 — exam submission lifecycle.
 *
 * submitted      objective questions only; final
 * pending_review at least one essay is waiting for manual teacher grading
 * pending_ai     automatic AI grading is still pending or failed
 * graded         every automatic and manual essay grade has been applied
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
