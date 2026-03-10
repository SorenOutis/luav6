<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'exam_id',
        'exam_part_id',
        'answers',
        'status',
        'score',
        'feedback',
    ];

    protected $casts = [
        'answers' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function examPart()
    {
        return $this->belongsTo(ExamPart::class);
    }
}
