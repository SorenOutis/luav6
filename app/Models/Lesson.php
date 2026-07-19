<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lesson extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'course_module_id',
        'title',
        'content',
        'video_url',
        'media_attachments',
        'sort_order',
        'admin_id',
    ];

    protected $casts = [
        'media_attachments' => 'array',
    ];

    /**
     * Get the module that owns this lesson.
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(CourseModule::class, 'course_module_id');
    }

    /**
     * Get the quiz for this lesson.
     */
    public function quiz(): HasOne
    {
        return $this->hasOne(LessonQuiz::class);
    }

    /**
     * Get the progress records for this lesson.
     */
    public function userProgress(): HasMany
    {
        return $this->hasMany(LessonUserProgress::class);
    }
}
