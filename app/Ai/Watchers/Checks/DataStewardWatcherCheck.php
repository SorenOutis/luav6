<?php

declare(strict_types=1);

namespace App\Ai\Watchers\Checks;

use App\Ai\Watchers\BotFinding;
use App\Ai\Watchers\BotWatcherCheck;
use App\Models\AiBot;
use App\Models\AiUsageLog;
use App\Models\Workspace;

/**
 * Data Steward: alerts before AI spend drifts without anyone noticing.
 *
 * Compares today's cost with the trailing week average in integer micros and
 * keeps a floor so tiny workspaces never trip on rounding noise.
 */
class DataStewardWatcherCheck implements BotWatcherCheck
{
    /** $0.20 in micros; below this, spikes are not worth an email. */
    public const FLOOR_MICROS = 200000;

    public const SPIKE_MULTIPLIER = 2.0;

    public const TRAIL_DAYS = 7;

    public function domain(): string
    {
        return 'data';
    }

    public function run(AiBot $bot, Workspace $workspace): array
    {
        $findings = [];

        $todayMicros = (int) AiUsageLog::query()
            ->where('workspace_id', $workspace->id)
            ->where('date', now()->toDateString())
            ->sum('estimated_cost_micros');

        $trailingMicros = (int) AiUsageLog::query()
            ->where('workspace_id', $workspace->id)
            ->where('date', '>=', now()->copy()->subDays(self::TRAIL_DAYS)->toDateString())
            ->where('date', '<', now()->toDateString())
            ->sum('estimated_cost_micros');

        $averageMicros = intdiv($trailingMicros, self::TRAIL_DAYS);

        if (
            $todayMicros >= self::FLOOR_MICROS
            && $averageMicros > 0
            && $todayMicros > $averageMicros * self::SPIKE_MULTIPLIER
        ) {
            $findings[] = new BotFinding(
                fingerprint: 'ai_spend_spike',
                severity: $todayMicros > $averageMicros * 5 ? 'critical' : 'warning',
                title: 'AI spend above the weekly norm',
                summary: sprintf(
                    'Today\'s AI cost is $%.2f, about %.1f× the trailing %d-day daily average of $%.2f.',
                    $todayMicros / 1_000_000,
                    $todayMicros / max($averageMicros, 1),
                    self::TRAIL_DAYS,
                    $averageMicros / 1_000_000,
                ),
                evidence: [
                    'Today: $'.number_format($todayMicros / 1_000_000, 4),
                    'Trailing average: $'.number_format($averageMicros / 1_000_000, 4),
                    'Floor: $'.number_format(self::FLOOR_MICROS / 1_000_000, 2),
                ],
                suggestedAction: 'Review the per-bot usage table on Bot Operations and consider lowering routine frequency or tightening budgets; budget changes apply workspace-wide.',
            );
        }

        return $findings;
    }
}
