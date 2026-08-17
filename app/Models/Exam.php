<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Exam extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'title',
        'description',
        'exam_date',
        'duration_minutes',
        'xp_rewards_enabled',
        'completion_xp',
        'on_time_xp',
        'accuracy_xp_enabled',
        'status',
        'ai_feedback_enabled',
        'ai_feedback_enabled_at',
        'url',
        'section_id',
        'workspace_id',
        'admin_id',
    ];

    protected $casts = [
        'exam_date' => 'datetime',
        'xp_rewards_enabled' => 'boolean',
        'accuracy_xp_enabled' => 'boolean',
        'ai_feedback_enabled' => 'boolean',
        'ai_feedback_enabled_at' => 'datetime',
    ];

    protected static function booted()
    {
        // The migration leaves historical exams disabled to avoid retroactive
        // double rewards. Newly created exams opt in unless explicitly disabled.
        static::creating(function (Exam $exam) {
            if (! array_key_exists('xp_rewards_enabled', $exam->getAttributes())) {
                $exam->xp_rewards_enabled = true;
            }
        });

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

    public function xpAwards()
    {
        return $this->hasMany(ExamXpAward::class);
    }
}
