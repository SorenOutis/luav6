<?php

namespace App\Models;

use App\Casts\ExamSubmissionAnswersCast;
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
            $wasSyncing = SectionProgress::$isSyncing;
            SectionProgress::$isSyncing = true;
            $sectionProgress->increment('points', $delta);
            $sectionProgress->save(); // Trigger sync
            SectionProgress::$isSyncing = $wasSyncing;

            // Academic marks and progression XP are intentionally separate.
            // Exam XP is granted once per completed exam by ExamXpAwardService.
            $user->recordGamificationHistory(0, $delta, $reason, $description, $exam->section_id);
        } elseif ($progress = $user->activeSeasonProgress()) {
            $wasSyncing = SectionProgress::$isSyncing;
            SectionProgress::$isSyncing = true;
            $progress->increment('points', $delta);
            $progress->save(); // Trigger sync
            SectionProgress::$isSyncing = $wasSyncing;

            $user->recordGamificationHistory(0, $delta, $reason, $description, null, $progress->season_id);
        } else {
            $user->increment('points', $delta);
            $user->save();

            $user->recordGamificationHistory(0, $delta, $reason, $description);
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
