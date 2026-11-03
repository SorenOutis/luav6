<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RunAiBotRoutine;
use App\Models\AiBotRoutine;
use App\Support\AiBotsGate;
use Cron\CronExpression;
use Illuminate\Console\Command;
use Throwable;

/**
 * Queues every active routine whose next cron occurrence is due.
 *
 * The command claims the routine by stamping `last_run_at` before dispatching,
 * so a slow queue cannot enqueue the same routine twice in one minute.
 */
class DispatchAiBotRoutines extends Command
{
    protected $signature = 'ai:bot-routines {--dry-run : List due routines without queueing them}';

    protected $description = 'Queue due AI bot routines';

    public function handle(): int
    {
        $now = now();
        $timezone = (string) config('app.timezone');
        $queued = 0;
        $invalid = 0;

        if (! AiBotsGate::enabled()) {
            $this->line('AI bots are disabled; no routines were dispatched.');

            return self::SUCCESS;
        }

        AiBotRoutine::query()
            ->withoutGlobalScope('workspace')
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (AiBotRoutine $routine) use ($now, $timezone, &$queued, &$invalid): void {
                try {
                    $due = $this->isDue($routine, $now, $timezone);
                } catch (Throwable $exception) {
                    $routine->update([
                        'last_status' => AiBotRoutine::STATUS_FAILED,
                        'last_error' => mb_substr('Invalid cron expression: '.$exception->getMessage(), 0, 2000),
                    ]);
                    $invalid++;

                    return;
                }

                if (! $due) {
                    return;
                }

                if ($this->option('dry-run')) {
                    $this->line("Due: #{$routine->id} {$routine->name} ({$routine->cron})");
                    $queued++;

                    return;
                }

                $claimed = AiBotRoutine::query()
                    ->withoutGlobalScope('workspace')
                    ->whereKey($routine->id)
                    ->where('is_active', true)
                    ->where(function ($query) use ($now): void {
                        $query->whereNull('last_run_at')
                            ->orWhere('last_run_at', '<', $now->copy()->startOfMinute());
                    })
                    ->update(['last_run_at' => $now]);

                if (! $claimed) {
                    return;
                }

                RunAiBotRoutine::dispatch($routine->id);
                $queued++;
            });

        $this->info("Queued {$queued} bot routine(s).".($invalid > 0 ? " {$invalid} invalid cron expression(s)." : ''));

        return self::SUCCESS;
    }

    private function isDue(AiBotRoutine $routine, \DateTimeInterface $now, string $timezone): bool
    {
        $from = $routine->last_run_at ?? $routine->created_at ?? $now;

        $next = (new CronExpression($routine->cron))
            ->getNextRunDate($from, 0, false, $timezone);

        return $next->getTimestamp() <= $now->getTimestamp();
    }
}
