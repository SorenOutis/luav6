<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'options' => 'array',
        'questions' => 'array',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function submissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }
}
