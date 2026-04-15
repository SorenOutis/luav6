<?php

namespace App\Models;

use App\Casts\ExamSubmissionAnswersCast;
use Illuminate\Database\Eloquent\Model;

class ExamSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'exam_id',
        'exam_part_id',
        'answers',
        'status',
        'score',
        'feedback',
    ];

    protected $casts = [
        'answers' => ExamSubmissionAnswersCast::class,
        'score' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (ExamSubmission $submission): void {
            self::applyScoreDeltaToStudent($submission, 0.0, self::scoreAsFloat($submission->score));
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

        $user->increment('points', $delta);
        $user->increment('exp', $delta);

        $progress = $user->activeSeasonProgress();
        if ($progress) {
            $progress->increment('points', $delta);
            $progress->increment('exp', $delta);
        }

        $exam = $submission->exam;
        if ($exam && $exam->section_id) {
            $sectionProgress = $user->activeSectionProgress($exam->section_id);
            $sectionProgress->increment('points', $delta);
            $sectionProgress->increment('exp', $delta);
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
