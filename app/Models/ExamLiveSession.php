<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamLiveSession extends Model
{
    protected $fillable = [
        'user_id',
        'exam_id',
        'exam_part_id',
        'status',
        'submitted_parts_count',
        'current_part_answered_count',
        'current_part_total_questions',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
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
