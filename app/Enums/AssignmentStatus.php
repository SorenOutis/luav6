<?php

namespace App\Enums;

/**
 * Assignment lifecycle.
 *
 * Mirrors App\Enums\ExamStatus so both content types share one mental
 * model: drafts are hidden while being prepared, published is open for
 * work, closed stays visible but no longer accepts submissions.
 */
enum AssignmentStatus: string
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

    /** Students may see the assignment in their listings. */
    public function isVisibleToStudents(): bool
    {
        return $this !== self::Draft;
    }

    /** Students may submit work (and form groups or invite members). */
    public function acceptsSubmissions(): bool
    {
        return $this === self::Published;
    }
}
