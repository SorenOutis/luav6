<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Exam extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'title',
        'description',
        'exam_date',
        'duration_minutes',
        'status',
        'ai_feedback_enabled',
        'ai_feedback_enabled_at',
        'url',
        'section_id',
        'admin_id',
    ];

    protected $casts = [
        'exam_date' => 'datetime',
        'ai_feedback_enabled' => 'boolean',
        'ai_feedback_enabled_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::updated(function ($exam) {
            Cache::forget("exam_structure_{$exam->id}");
        });

        static::deleted(function ($exam) {
            Cache::forget("exam_structure_{$exam->id}");
        });
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function parts()
    {
        return $this->hasMany(ExamPart::class)->orderBy('sort_order');
    }

    public function submissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }
}
