<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ai\Watchers\AiBotWatcherService;
use App\Mail\AiBotFindingAlertMail;
use App\Models\AiBotFinding;
use App\Models\Workspace;
use App\Support\AiBotsGate;
use App\Support\AiBotWatchers;
use App\Support\WorkspaceContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Executes one workspace's watcher pass: every seeded specialist performs its
 * read-only domain check, findings are deduped, reasoning lands in the bot
 * team room, and new actionable findings trigger one instant alert email.
 *
 * Uniqueness is per workspace so a slow pass over one tenant cannot stack up
 * duplicate runs while the scheduler keeps firing every 30 minutes.
 *
 * Privacy: findings, team messages, and emails carry counts and ages only.
 * User message contents, transcript excerpts addressed to students, and raw
 * approval payloads never appear in watcher output.
 */
class RunAiBotWatchers implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public bool $failOnTimeout = true;

    public function __construct(public int $workspaceId)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->workspaceId;
    }

    public function handle(AiBotWatcherService $watchers): void
    {
        if (! AiBotsGate::enabled() || ! AiBotWatchers::enabled()) {
            return;
        }

        $workspace = Workspace::query()->find($this->workspaceId);

        if (! $workspace || $workspace->archived_at !== null) {
            return;
        }

        // Checks query workspace-scoped models, so the tenant context must be
        // explicit for the whole pass; a queue worker has no session.
        $runs = app(WorkspaceContext::class)->run($this->workspaceId, function () use ($watchers, $workspace) {
            $alertable = [];

            try {
                $alertable = $watchers->runForWorkspace($workspace);
            } catch (Throwable $exception) {
                // A failing check for one bot must not abort the email for the
                // others; nothing is retried (the cadence covers the next run).
                Log::error('Bot watcher pass failed', [
                    'workspace_id' => $workspace->id,
                    'error' => Str::limit($exception->getMessage(), 500, ''),
                ]);
            }

            return $alertable;
        });

        $pending = AiBotFinding::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspace->id)
            ->where('status', AiBotFinding::STATUS_OPEN)
            ->where('severity', '!=', AiBotFinding::SEVERITY_INFO)
            ->whereNotNull('suggested_action')
            ->whereNull('notified_at')
            ->orderBy('severity', 'desc')
            ->get();

        // Findings whose email previously failed stay queued for the next run;
        // escalations re-alert even when first seen earlier.
        $all = collect($runs)
            ->merge($pending)
            ->unique(fn (AiBotFinding $finding): int => (int) $finding->getKey())
            ->take(self::MAX_FINDINGS_PER_EMAIL);

        if ($all->isEmpty()) {
            return;
        }

        $this->notify($workspace, $all->values()->all());
    }

    public const MAX_FINDINGS_PER_EMAIL = 12;

    /**
     * @param  list<AiBotFinding>  $alertable
     */
    private function notify(Workspace $workspace, array $alertable): void
    {
        $email = AiBotWatchers::alertEmail();

        if (! $email) {
            return;
        }

        try {
            // sendNow keeps the notified_at stamp honest: a queued-but-never-
            // delivered email would otherwise mark findings as notified.
            Mail::to($email)->sendNow(new AiBotFindingAlertMail($workspace, $alertable));
        } catch (Throwable $exception) {
            Log::error('Bot watcher alert email failed', [
                'workspace_id' => $workspace->id,
                'findings' => count($alertable),
                'error' => Str::limit($exception->getMessage(), 500, ''),
            ]);

            return;
        }

        foreach ($alertable as $finding) {
            $finding->forceFill(['notified_at' => now()])->save();
        }
    }
}
