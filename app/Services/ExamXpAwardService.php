<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamSet;
use App\Models\ExamSubmission;
use App\Models\ExamXpAward;
use App\Models\SectionProgress;
use App\Models\User;
use App\Support\GamificationSyncContext;
use Illuminate\Support\Facades\DB;

class ExamXpAwardService
{
    public function __construct(protected ExamSetAssignmentService $examSets) {}

    /**
     * Grant any newly eligible XP components and return the durable award.
     * This method is intentionally idempotent: it is called by the final submit,
     * asynchronous essay grading, and the student's grading-status poll.
     */
    public function awardIfEligible(User $user, Exam $exam, ?ExamSet $set = null): ?ExamXpAward
    {
        if (! $exam->xp_rewards_enabled) {
            return null;
        }

        // XP is earned against the set the student actually took, so a student
        // on a two-part set is not held to the part count of another set.
        $set ??= $this->examSets->resolveSet($exam, $user);
        $parts = $this->examSets->filterParts($exam, $this->examSets->structure($exam), $set);

        if ($parts->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($user, $exam, $parts): ?ExamXpAward {
            $partIds = $parts->pluck('id');
            $submissions = ExamSubmission::query()
                ->where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->whereIn('exam_part_id', $partIds)
                ->get();

            if ($submissions->unique('exam_part_id')->count() !== $partIds->count()) {
                return null;
            }

            $award = ExamXpAward::query()->firstOrCreate(
                ['user_id' => $user->id, 'exam_id' => $exam->id],
                ['completed_at' => now()],
            );
            $award = ExamXpAward::query()->lockForUpdate()->findOrFail($award->id);

            $components = [];

            if ((int) $award->completion_xp === 0 && (int) $exam->completion_xp > 0) {
                $amount = (int) $exam->completion_xp;
                $award->completion_xp = $amount;
                $components[] = ['amount' => $amount, 'reason' => 'Exam Completion XP'];
            }

            if ((int) $award->on_time_xp === 0
                && (int) $exam->on_time_xp > 0
                && $submissions->every(fn (ExamSubmission $submission) => ! $submission->is_late)) {
                $amount = (int) $exam->on_time_xp;
                $award->on_time_xp = $amount;
                $components[] = ['amount' => $amount, 'reason' => 'On-time Exam XP'];
            }

            $gradingComplete = $submissions->every(
                fn (ExamSubmission $submission) => ! in_array($submission->status, ['pending_review', 'pending_ai'], true)
                    && ! $submission->grading_failed
            );

            if ($gradingComplete && ! $award->accuracy_finalized_at) {
                $possible = $parts->sum(function ($part): float {
                    return collect($part->questions ?? [])->sum(
                        fn (array $question): float => (float) ($question['points'] ?? $part->points ?? 1)
                    );
                });
                $percentage = $possible > 0
                    ? round(((float) $submissions->sum('score') / $possible) * 100, 2)
                    : 0.0;
                $accuracyXp = $exam->accuracy_xp_enabled ? $this->accuracyXpFor($percentage) : 0;

                $award->accuracy_percentage = $percentage;
                $award->accuracy_finalized_at = now();

                if ($accuracyXp > (int) $award->accuracy_xp) {
                    $delta = $accuracyXp - (int) $award->accuracy_xp;
                    $award->accuracy_xp = $accuracyXp;
                    $components[] = ['amount' => $delta, 'reason' => 'Exam Accuracy XP'];
                }
            }

            $award->save();

            foreach ($components as $component) {
                $this->grant(
                    $user,
                    $exam,
                    (int) $component['amount'],
                    (string) $component['reason'],
                );
            }

            return $award->fresh();
        });
    }

    public function serialize(?ExamXpAward $award): ?array
    {
        if (! $award) {
            return null;
        }

        return [
            'completion_xp' => (int) $award->completion_xp,
            'accuracy_xp' => (int) $award->accuracy_xp,
            'on_time_xp' => (int) $award->on_time_xp,
            'total_xp' => $award->total(),
            'accuracy_percentage' => $award->accuracy_percentage !== null
                ? (float) $award->accuracy_percentage
                : null,
            'accuracy_pending' => $award->accuracy_finalized_at === null,
        ];
    }

    private function accuracyXpFor(float $percentage): int
    {
        return match (true) {
            $percentage >= 95 => 15,
            $percentage >= 85 => 10,
            $percentage >= 70 => 5,
            default => 0,
        };
    }

    private function grant(User $user, Exam $exam, int $amount, string $reason): void
    {
        if ($amount <= 0) {
            return;
        }

        $description = $reason.' for Exam: '.$exam->title;

        $context = app(GamificationSyncContext::class);

        if ($exam->section_id) {
            $progress = $user->activeSectionProgress($exam->section_id);
            $context->withoutAutomaticHistory(function () use ($progress, $amount): void {
                $progress->increment('exp', $amount);
                $progress->save();
            });
            $user->recordGamificationHistory($amount, 0, $reason, $description, $exam->section_id);

            return;
        }

        if ($progress = $user->activeSeasonProgress()) {
            // SeasonProgress synchronizes the user total. Suppress only its
            // generic adjustment history because we record a precise reason.
            $context->withoutAutomaticHistory(function () use ($progress, $amount): void {
                $progress->increment('exp', $amount);
                $progress->save();
            });
            $user->recordGamificationHistory($amount, 0, $reason, $description, null, $progress->season_id);

            return;
        }

        $user->increment('exp', $amount);
        $user->level = SectionProgress::levelFromExp($user->exp);
        $user->save();
        $user->recordGamificationHistory($amount, 0, $reason, $description);
    }
}
