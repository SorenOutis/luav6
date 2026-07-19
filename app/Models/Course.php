<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'name',
        'description',
        'total_lessons',
        'cover_photo',
        'admin_id',
    ];

    protected $casts = [
        'total_lessons' => 'integer',
    ];

    /**
     * Get the cover photo URL.
     */
    public function getCoverPhotoUrlAttribute(): ?string
    {
        if (! $this->cover_photo) {
            return null;
        }

        return asset('storage/'.$this->cover_photo);
    }

    /**
     * The users (students) enrolled in this course.
     */
    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('completed_lessons', 'xp_earned', 'next_deadline')->withTimestamps();
    }

    /**
     * The modules in this course.
     */
    public function modules()
    {
        return $this->hasMany(CourseModule::class)->orderBy('sort_order');
    }

    /**
     * Get completed lesson count for a specific user.
     */
    public function completedLessonsForUser(User $user): int
    {
        $lessonIds = $this->modules()
            ->with('lessons')
            ->get()
            ->pluck('lessons.*.id')
            ->flatten();

        return LessonUserProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('completed', true)
            ->count();
    }

    /**
     * Get total lessons count across all modules.
     */
    public function getTotalLessonsCountAttribute(): int
    {
        return $this->modules()
            ->withCount('lessons')
            ->get()
            ->sum('lessons_count');
    }
}
