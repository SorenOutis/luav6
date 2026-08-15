<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamXpAward extends Model
{
    protected $fillable = [
        'user_id',
        'exam_id',
        'completion_xp',
        'accuracy_xp',
        'on_time_xp',
        'accuracy_percentage',
        'completed_at',
        'accuracy_finalized_at',
    ];

    protected $casts = [
        'accuracy_percentage' => 'decimal:2',
        'completed_at' => 'datetime',
        'accuracy_finalized_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function total(): int
    {
        return (int) $this->completion_xp + (int) $this->accuracy_xp + (int) $this->on_time_xp;
    }
}
