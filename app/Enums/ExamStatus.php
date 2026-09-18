<?php

namespace App\Enums;

/**
 * Phase 4.1 — exam lifecycle.
 *
 * Replaces raw 'draft' / 'published' / 'closed' strings, where a typo was a
 * silent bug rather than an error.
 */
enum ExamStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Closed => 'Closed',
        };
    }

    /** Students may see the exam listed. */
    public function isVisibleToStudents(): bool
    {
        return $this !== self::Draft;
    }

    /** Students may submit answers. */
    public function acceptsSubmissions(): bool
    {
        return $this === self::Published;
    }

    /**
     * Answer keys may be revealed.
     *
     * Product decision: review after the exam closes, not immediately after a
     * student submits — otherwise students in the same room can share answers
     * while others are still working.
     */
    public function allowsAnswerReview(): bool
    {
        return $this === self::Closed;
    }
}
