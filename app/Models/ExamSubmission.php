<?php

namespace App\Models;

use App\Casts\ExamSubmissionAnswersCast;
use App\Support\LevelCurve;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'exam_id',
        'exam_part_id',
        'answers',
        'status',
        'is_late',
        'grading_failed',
        'score',
        'feedback',
    ];

    protected $casts = [
        'answers' => ExamSubmissionAnswersCast::class,
        'score' => 'decimal:2',
        'is_late' => 'boolean',
        'grading_failed' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (ExamSubmission $submission): void {
            self::applyScoreDeltaToStudent($submission, 0.0, self::scoreAsFloat($submission->score));
        });

        static::deleted(function (ExamSubmission $submission): void {
            // When a submission is deleted, subtract its score from the student's progress
            $oldScore = self::scoreAsFloat($submission->score);
            self::applyScoreDeltaToStudent($submission, $oldScore, 0.0);
        });

        static::updated(function (ExamSubmission $submission): void {
            if (! $submission->wasChanged('score')) {
                return;
            }

            $old = self::scoreAsFloat($submission->getOriginal('score'));
            $new = self::scoreAsFloat($submission->score);

            self::applyScoreDeltaToStudent($submission, $old, $new);
        });
    }

    private static function scoreAsFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private static function applyScoreDeltaToStudent(ExamSubmission $submission, float $previousScore, float $newScore): void
    {
        $delta = round($newScore - $previousScore, 2);

        if (abs($delta) < 0.005 || ! $submission->user_id) {
            return;
        }

        $user = $submission->user;
        $exam = $submission->exam;
        $reason = 'Exam Submission';
        $description = 'Earned from Exam: '.($exam?->title ?? 'Unknown');

        if ($exam && $exam->section_id) {
            $sectionProgress = $user->activeSectionProgress($exam->section_id);
            $sectionProgress->increment('points', $delta);
            $sectionProgress->increment('exp', $delta);
            $sectionProgress->save(); // Trigger sync

            $user->recordGamificationHistory($delta, $delta, $reason, $description, $exam->section_id);
        } elseif ($progress = $user->activeSeasonProgress()) {
            $progress->increment('points', $delta);
            $progress->increment('exp', $delta);
            $progress->save(); // Trigger sync

            $user->recordGamificationHistory($delta, $delta, $reason, $description, null, $progress->season_id);
        } else {
            $user->increment('points', $delta);
            $user->increment('exp', $delta);
            $user->level = LevelCurve::levelForXp((int) $user->exp);
            $user->save();

            $user->recordGamificationHistory($delta, $delta, $reason, $description);
        }
    }

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
