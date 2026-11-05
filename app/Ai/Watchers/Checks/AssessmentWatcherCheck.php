<?php

declare(strict_types=1);

namespace App\Ai\Watchers\Checks;

use App\Ai\Watchers\BotFinding;
use App\Ai\Watchers\BotWatcherCheck;
use App\Models\AiBot;
use App\Models\ExamSubmission;
use App\Models\Workspace;

/**
 * Assessment Officer: grading backlog is student-visible debt.
 */
class AssessmentWatcherCheck implements BotWatcherCheck
{
    public const WAITING_HOURS = 6;

    public function domain(): string
    {
        return 'assessment';
    }

    public function run(AiBot $bot, Workspace $workspace): array
    {
        $findings = [];
        $cutoff = now()->subHours(self::WAITING_HOURS);

        $backlog = ExamSubmission::query()
            ->whereHas('exam', fn ($query) => $query->where('workspace_id', $workspace->id))
            ->where(function ($query) {
                $query->whereIn('status', ['pending_ai', 'pending_review'])
                    ->orWhere('grading_failed', true);
            })
            ->where('updated_at', '<', $cutoff)
            ->with('exam:id,title')
            ->limit(50)
            ->get();

        if ($backlog->isNotEmpty()) {
            $failed = $backlog->filter(fn (ExamSubmission $submission) => (bool) $submission->grading_failed)->count();

            $findings[] = new BotFinding(
                fingerprint: 'submissions_awaiting_grading',
                severity: $backlog->count() >= 15 ? 'critical' : 'warning',
                title: 'Submissions waiting for grading',
                summary: sprintf(
                    '%d submission(s) have waited more than %d hours for a grade; %d of them failed automatic grading.',
                    $backlog->count(),
                    self::WAITING_HOURS,
                    $failed,
                ),
                evidence: [
                    'Submissions pending or failed for '.self::WAITING_HOURS.'h+: '.$backlog->count(),
                    'Grading failures included: '.$failed,
                    'Exams involved: '.implode(', ', $backlog->pluck('exam.title')->filter()->unique()->take(5)->all()),
                ],
                suggestedAction: 'Triage the oldest submissions and draft grading exemptions where scores are blocked; grades change only after approval.',
            );
        }

        return $findings;
    }
}
