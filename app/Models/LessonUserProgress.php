<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonUserProgress extends Model
{
    protected $fillable = [
        'user_id',
        'lesson_id',
        'completed',
        'quiz_score',
        'attempts',
        'quiz_answers',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'quiz_score' => 'integer',
        'attempts' => 'integer',
        'quiz_answers' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns this progress record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the lesson this progress is for.
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
