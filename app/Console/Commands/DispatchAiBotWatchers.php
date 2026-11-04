<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunAiBotWatchers;
use App\Models\Workspace;
use App\Support\AiBotsGate;
use App\Support\AiBotWatchers;
use Illuminate\Console\Command;

/**
 * Queues one watcher pass per non-archived workspace, every 30 minutes.
 *
 * The per-workspace job unique locks prevent overlap; this command itself is
 * idempotent at the cadence level and cheap when gates are off.
 */
class DispatchAiBotWatchers extends Command
{
    protected $signature = 'ai:bot-watchers {--dry-run : List workspaces whose watchers would run}';

    protected $description = 'Queue AI bot watcher passes for every non-archived workspace';

    public function handle(): int
    {
        if (! AiBotsGate::enabled()) {
            $this->line('AI bots are disabled; no watcher passes were dispatched.');

            return self::SUCCESS;
        }

        if (! AiBotWatchers::enabled()) {
            $this->line('Bot watchers are disabled; no watcher passes were dispatched.');

            return self::SUCCESS;
        }

        $workspaces = Workspace::query()
            ->whereNull('archived_at')
            ->orderBy('id')
            ->get();

        if ($this->option('dry-run')) {
            foreach ($workspaces as $workspace) {
                $this->line("Watcher due: workspace #{$workspace->id} {$workspace->name}");
            }

            $this->info("{$workspaces->count()} watcher pass(es) due.");

            return self::SUCCESS;
        }

        foreach ($workspaces as $workspace) {
            RunAiBotWatchers::dispatch($workspace->id);
        }

        $this->info("Queued {$workspaces->count()} bot watcher pass(es).");

        return self::SUCCESS;
    }
}
