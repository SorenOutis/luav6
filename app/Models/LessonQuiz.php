<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonQuiz extends Model
{
    protected $fillable = [
        'lesson_id',
        'questions',
        'pass_score',
        'allowed_attempts',
    ];

    protected $casts = [
        'questions' => 'array',
        'pass_score' => 'integer',
        'allowed_attempts' => 'integer',
    ];

    /**
     * Get the lesson that owns this quiz.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
