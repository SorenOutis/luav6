<?php

declare(strict_types=1);

namespace App\Ai\Watchers\Checks;

use App\Ai\Watchers\BotFinding;
use App\Ai\Watchers\BotWatcherCheck;
use App\Models\AiBot;
use App\Models\AiBotHandoff;
use App\Models\AiBotRoutine;
use App\Models\Workspace;
use Illuminate\Support\Str;

/**
 * Operations Controller: the bot engine watching itself.
 *
 * Stale queue entries and failed routines mean lost delegated work; this is
 * the only check whose subject is the bot system rather than the workspace.
 */
class OperationsWatcherCheck implements BotWatcherCheck
{
    public const STALE_QUEUE_MINUTES = 60;

    public function domain(): string
    {
        return 'operations';
    }

    public function run(AiBot $bot, Workspace $workspace): array
    {
        $findings = [];
        $cutoff = now()->subMinutes(self::STALE_QUEUE_MINUTES);

        $staleHandoffs = AiBotHandoff::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspace->id)
            ->where('status', AiBotHandoff::STATUS_QUEUED)
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->limit(20)
            ->get();

        if ($staleHandoffs->isNotEmpty()) {
            $oldest = (int) $staleHandoffs->first()->created_at->diffInMinutes(now());

            $findings[] = new BotFinding(
                fingerprint: 'stale_queued_handoffs',
                severity: $staleHandoffs->count() >= 5 ? 'critical' : 'warning',
                title: 'Delegated tasks stuck in queue',
                summary: sprintf(
                    '%d specialist task(s) have sat in the queue for over %d minutes; the oldest has waited %d minutes.',
                    $staleHandoffs->count(),
                    self::STALE_QUEUE_MINUTES,
                    $oldest,
                ),
                evidence: [
                    'Queued handoffs older than '.self::STALE_QUEUE_MINUTES.'m: '.$staleHandoffs->count(),
                    'Oldest waiting: '.$oldest.'m',
                ],
                suggestedAction: 'Check that the Horizon worker is running and review the handoff matrix on Bot Operations; nothing auto-retries these.',
            );
        }

        $failedRoutines = AiBotRoutine::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspace->id)
            ->where('is_active', true)
            ->where('last_status', AiBotRoutine::STATUS_FAILED)
            ->orderBy('name')
            ->get();

        if ($failedRoutines->isNotEmpty()) {
            $names = $failedRoutines->map(fn (AiBotRoutine $routine) => Str::limit($routine->name, 40))->take(5)->implode(', ');

            $findings[] = new BotFinding(
                fingerprint: 'failed_routines',
                severity: 'warning',
                title: 'Routines failing on schedule',
                summary: sprintf(
                    '%d active routine(s) last ran into a failure and will keep retrying on their cron schedule.',
                    $failedRoutines->count(),
                ),
                evidence: [
                    'Failed routines: '.$failedRoutines->count(),
                    'Names: '.$names,
                ],
                suggestedAction: 'Open Bot Operations, inspect each failing routine, and confirm the AI provider, model, and daily limits are healthy.',
            );
        }

        return $findings;
    }
}
