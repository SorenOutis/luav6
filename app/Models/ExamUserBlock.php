<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student a teacher has barred from one exam.
 *
 * One row per (exam, student). The exam stays visible to everybody else in the
 * section; the blocked student simply never sees it, whatever its status or
 * schedule.
 */
class ExamUserBlock extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'blocked_by',
        'reason',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The admin who wrote the block. */
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }
}
