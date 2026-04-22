<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Exam extends Model
{
    protected $fillable = [
        'title',
        'description',
        'exam_date',
        'duration_minutes',
        'status',
        'url',
        'section_id',
    ];

    protected $casts = [
        'exam_date' => 'datetime',
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
