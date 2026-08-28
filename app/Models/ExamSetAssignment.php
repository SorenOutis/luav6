<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The set a single student was handed for a single exam.
 *
 * Written once, on the student's first interaction with the exam, and never
 * rotated — resuming an attempt must always show the same questions.
 */
class ExamSetAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'exam_set_id',
        'user_id',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examSet(): BelongsTo
    {
        return $this->belongsTo(ExamSet::class, 'exam_set_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
