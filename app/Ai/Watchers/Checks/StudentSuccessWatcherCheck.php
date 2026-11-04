<?php

declare(strict_types=1);

namespace App\Ai\Watchers\Checks;

use App\Ai\Watchers\BotFinding;
use App\Ai\Watchers\BotWatcherCheck;
use App\Models\AiBot;
use App\Models\User;
use App\Models\Workspace;

/**
 * Student Success Coach: students drifting away need outreach soon.
 *
 * Mirrors the at_risk_students read tool (30-day window, low exam averages)
 * but reports only counts, never the rosters, to keep findings PII-light.
 */
class StudentSuccessWatcherCheck implements BotWatcherCheck
{
    public const INACTIVE_DAYS = 30;

    public const LOW_AVERAGE = 60.0;

    public const WARNING_COUNT = 3;

    public const CRITICAL_SHARE = 0.25;

    public function domain(): string
    {
        return 'student-success';
    }

    public function run(AiBot $bot, Workspace $workspace): array
    {
        $findings = [];
        $now = now();
        $cutoff = $now->copy()->subDays(self::INACTIVE_DAYS);

        $students = User::query()
            ->where(function ($query) use ($workspace) {
                $query->whereHas('workspaces', fn ($q) => $q->whereKey($workspace->id))
                    ->orWhereHas('sections', fn ($q) => $q->where('sections.workspace_id', $workspace->id))
                    ->orWhere('current_workspace_id', $workspace->id);
            })
            ->where('is_admin', false)
            ->withAvg(['examSubmissions as graded_average' => fn ($q) => $q->where('status', 'graded')], 'score')
            ->withCount(['examSubmissions as graded_count' => fn ($q) => $q->where('status', 'graded')])
            ->get();

        $atRisk = 0;
        $inactive = 0;

        foreach ($students as $student) {
            $reasons = 0;

            if (! $student->last_login_at || $student->last_login_at->lt($cutoff)) {
                $inactive++;
                $reasons++;
            }

            $graded = (int) $student->graded_count;
            $average = $student->graded_average !== null ? (float) $student->graded_average : null;

            if ($graded === 0 || ($average !== null && $average < self::LOW_AVERAGE)) {
                $reasons++;
            }

            if ($reasons >= 2) {
                $atRisk++;
            }
        }

        if ($atRisk >= self::WARNING_COUNT) {
            $share = $students->count() > 0 ? $atRisk / $students->count() : 0.0;

            $findings[] = new BotFinding(
                fingerprint: 'at_risk_students',
                severity: $share >= self::CRITICAL_SHARE ? 'critical' : 'warning',
                title: 'Students drifting at risk',
                summary: sprintf(
                    '%d students are both inactive for %d+ days and behind on graded work (%.1f%% of the roster).',
                    $atRisk,
                    self::INACTIVE_DAYS,
                    $share * 100,
                ),
                evidence: [
                    'Students with combined risk: '.$atRisk,
                    'Students scoring below '.self::LOW_AVERAGE.' average or without graded work: see at_risk_students report for names',
                    'Inactive beyond '.self::INACTIVE_DAYS.' days: '.$inactive,
                ],
                suggestedAction: 'Line up check-in outreach for the highest-risk students; bulk notifications must be approved before sending.',
            );
        }

        return $findings;
    }
}
