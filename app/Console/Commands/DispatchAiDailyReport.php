<?php

namespace App\Console\Commands;

use App\Services\AiDailyReportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DispatchAiDailyReport extends Command
{
    protected $signature = 'ai:daily-report {--date= : Report a complete past local calendar day (YYYY-MM-DD)}';

    protected $description = 'Queue the configured read-only nightly AI report for the super admin';

    public function handle(AiDailyReportService $reports): int
    {
        try {
            $report = $reports->dispatch($this->option('date'));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info($report
            ? "Daily report #{$report->id} tracked for {$report->report_date->toDateString()}."
            : 'No daily report queued: disabled, not configured, or before the scheduled time.');

        return self::SUCCESS;
    }
}
