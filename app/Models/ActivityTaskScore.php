<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityTaskScore extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_task_id',
        'user_id',
        'score',
        'is_missed',
        'remarks',
        'graded_by',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'is_missed' => 'boolean',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(ActivityTask::class, 'activity_task_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function percentage(): ?float
    {
        if ($this->score === null || ! $this->task || (float) $this->task->max_points <= 0) {
            return null;
        }

        return round(((float) $this->score / (float) $this->task->max_points) * 100, 1);
    }
}
