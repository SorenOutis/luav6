<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Support\MatchingAnswerMatcher;
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

    /**
     * Calculate the total possible points for this part from its questions.
     */
    public function totalPoints(): float
    {
        $questions = is_array($this->questions) ? $this->questions : [];
        if (empty($questions)) {
            return (float) ($this->points ?? 0);
        }

        $defaultPoints = (int) ($this->points ?? 1);
        $total = 0.0;

        foreach ($questions as $question) {
            if (! is_array($question)) {
                continue;
            }

            $type = QuestionType::tryFromStored($question['type'] ?? null) ?? QuestionType::MultipleChoice;

            if ($type === QuestionType::Enumeration) {
                $enumerationItems = collect($question['enumeration_items'] ?? [])
                    ->filter(fn ($item): bool => is_array($item))
                    ->map(fn (array $item): float => (float) ($item['points'] ?? 0));
                $total += $enumerationItems->sum();

                continue;
            }

            if ($type === QuestionType::Matching) {
                $total += MatchingAnswerMatcher::maxPoints($question);

                continue;
            }

            $total += (float) ($question['points'] ?? $defaultPoints);
        }

        return round($total, 2);
    }
}
