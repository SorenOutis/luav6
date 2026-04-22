<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ExamPart extends Model
{
    protected $fillable = [
        'exam_id',
        'title',
        'instructions',
        'type',
        'sort_order',
        'options',
        'questions',
        'points',
    ];

    protected $casts = [
        'options' => 'array',
        'questions' => 'array',
    ];

    protected static function booted()
    {
        static::saved(function ($part) {
            Cache::forget("exam_structure_{$part->exam_id}");
        });

        static::deleted(function ($part) {
            Cache::forget("exam_structure_{$part->exam_id}");
        });
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function submissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }
}
