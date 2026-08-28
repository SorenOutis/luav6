<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * One interchangeable version ("Set A", "Set B", …) of an exam.
 *
 * A set owns its own parts and questions. Students are handed a set when they
 * start the exam and keep it for the whole attempt — see
 * App\Services\ExamSetAssignmentService.
 */
class ExamSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_id',
        'title',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // Admin convenience: sets added without a title are named in rotation
        // order (Set A, Set B, …) instead of being left blank.
        static::creating(function (ExamSet $set): void {
            if (blank($set->title)) {
                $set->title = static::titleForIndex(
                    (int) static::query()->where('exam_id', $set->exam_id)->count()
                );
            }

            if (blank($set->sort_order)) {
                $set->sort_order = static::nextSortOrder((int) $set->exam_id);
            }
        });

        static::saved(function (ExamSet $set): void {
            Cache::forget("exam_structure_{$set->exam_id}");
        });

        // Deleting a set cascades to its parts (and their submissions), so the
        // cached structure has to go too.
        static::deleted(function (ExamSet $set): void {
            Cache::forget("exam_structure_{$set->exam_id}");
        });
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(ExamPart::class)->orderBy('sort_order');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ExamSetAssignment::class);
    }

    /**
     * Human label for a zero-based rotation index: 0 → "Set A", 26 → "Set 27".
     */
    public static function titleForIndex(int $index): string
    {
        if ($index < 0) {
            $index = 0;
        }

        if ($index < 26) {
            return 'Set '.chr(ord('A') + $index);
        }

        return 'Set '.($index + 1);
    }

    public static function nextSortOrder(int $examId): int
    {
        return ((int) static::query()->where('exam_id', $examId)->max('sort_order')) + 1;
    }

    /**
     * The set that owns any part created without an explicit set.
     *
     * Every exam has at least one set, so old write paths (CSV import, AI
     * drafts, factories) keep working untouched: their parts simply land in the
     * first set.
     */
    public static function ensureDefaultForExam(int $examId): ExamSet
    {
        $existing = static::query()
            ->where('exam_id', $examId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return $existing ?? static::query()->create([
            'exam_id' => $examId,
            'title' => static::titleForIndex(0),
            'sort_order' => 0,
        ]);
    }
}
