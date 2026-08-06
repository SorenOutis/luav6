<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Spawns a detached `php artisan queue:work` worker on-demand to process the
 * "ai" queue. The worker uses --stop-when-empty, so it exits by itself once
 * all AI feedback jobs have finished. This removes the need for the admin to
 * manually run `php artisan queue:work` on their machine.
 */
class AiQueueWorker
{
    /**
     * Start a background worker for the "ai" queue if one is not already
     * running for this project. Safe to call multiple times: the database
     * queue driver atomically reserves jobs, so duplicate workers will
     * simply idle and exit.
     */
    public static function ensureRunning(string $queue = 'ai'): void
    {
        // Never spawn real OS processes from the test suite.
        if (app()->runningUnitTests()) {
            return;
        }

        try {
            $phpBinary = (new PhpExecutableFinder)->find(false) ?: 'php';
            $artisan = base_path('artisan');
            $cwd = base_path();

            // --stop-when-empty => worker exits when queue is empty.
            // --tries=3         => transient provider blips retry instead of
            //                     permanently failing a job (matches the essay
            //                     grading job's own $tries).
            // --timeout=0       => no worker-level cap; per-job $timeout
            //                     (e.g. 300s for essay grading) governs.
            // --sleep=1         => poll every second.
            // --max-time=3600   => hard cap so a stuck worker self-terminates.
            $args = [
                $phpBinary,
                $artisan,
                'queue:work',
                '--queue='.$queue,
                '--stop-when-empty',
                '--tries=3',
                '--timeout=0',
                '--sleep=1',
                '--max-time=3600',
            ];

            if (PHP_OS_FAMILY === 'Windows') {
                // On Windows we use `start /B` via cmd so the spawned process
                // is fully detached from the PHP-FPM worker. Symfony's
                // Process::start() keeps file handles that FPM can hold open.
                $cmd = 'start /B "" '.self::joinArgs($args).' > NUL 2>&1';
                pclose(popen('cmd /c '.$cmd, 'r'));

                return;
            }

            // Unix: use Symfony Process detached.
            $process = new Process($args, $cwd);
            $process->setTimeout(null);
            $process->disableOutput();
            $process->start();
        } catch (\Throwable $e) {
            Log::warning('Failed to spawn AI queue worker: '.$e->getMessage());
        }
    }

    /**
     * Quote and join an argv array for a Windows cmd.exe command line.
     */
    private static function joinArgs(array $args): string
    {
        return implode(' ', array_map(static function (string $a): string {
            if ($a === '' || preg_match('/[\s"]/', $a)) {
                return '"'.str_replace('"', '\\"', $a).'"';
            }

            return $a;
        }, $args));
    }
}
