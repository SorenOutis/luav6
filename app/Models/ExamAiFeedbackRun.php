<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamAiFeedbackRun extends Model
{
    protected $fillable = [
        'exam_id',
        'status',
        'total_essays',
        'processed_essays',
        'skipped_essays',
        'current_user_name',
        'current_part_title',
        'last_error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }
}
