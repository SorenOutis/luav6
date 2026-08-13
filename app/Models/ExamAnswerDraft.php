<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAnswerDraft extends Model
{
    protected $fillable = [
        'user_id',
        'exam_id',
        'exam_part_id',
        'answers',
        'saved_at',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'saved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function examPart(): BelongsTo
    {
        return $this->belongsTo(ExamPart::class);
    }
}
