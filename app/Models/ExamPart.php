<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ExamPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'exam_set_id',
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
        // Every part belongs to exactly one set. Parts created without one
        // (CSV import, AI question drafts, factories, tests) fall into the
        // exam's first set, and parts created through a set inherit the exam
        // from that set — so `exam_id` and `exam_set_id` can never drift apart.
        static::creating(function (ExamPart $part): void {
            if (filled($part->exam_set_id) && blank($part->exam_id)) {
                $part->exam_id = ExamSet::query()
                    ->whereKey($part->exam_set_id)
                    ->value('exam_id');
            }

            if (blank($part->exam_set_id) && filled($part->exam_id)) {
                $part->exam_set_id = ExamSet::ensureDefaultForExam((int) $part->exam_id)->id;
            }
        });

        static::saved(function ($part) {
            Cache::forget("exam_structure_{$part->exam_id}");
        });

        // A set only enters the deal once it holds questions, so the moment an
        // empty set gains its first part it becomes available: students who
        // have not started yet are re-dealt so they can actually receive it.
        static::created(function (ExamPart $part): void {
            if (blank($part->exam_set_id)) {
                return;
            }

            $isFirstPartOfSet = static::query()
                ->where('exam_set_id', $part->exam_set_id)
                ->whereKeyNot($part->getKey())
                ->doesntExist();

            if (! $isFirstPartOfSet) {
                return;
            }

            $part->examSet()->first()?->redealUnstartedStudents();
        });

        static::deleted(function ($part) {
            Cache::forget("exam_structure_{$part->exam_id}");
        });
    }

    public function exam()
    {
        return $this->belongsTo(Exam::class);
    }

    public function examSet(): BelongsTo
    {
        return $this->belongsTo(ExamSet::class);
    }

    public function submissions()
    {
        return $this->hasMany(ExamSubmission::class);
    }
}
