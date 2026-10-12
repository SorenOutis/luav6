<?php

namespace App\Jobs;

use App\Models\AiDailyReport;
use App\Services\AiDailyReportAnalystService;
use App\Services\AiDailyReportService;
use App\Services\AiDailyReportSnapshotService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateAiDailyReport implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 70;

    public int $uniqueFor = 600;

    public bool $failOnTimeout = true;

    public function __construct(public int $reportId)
    {
        $this->onQueue(config('ai_reports.queue', 'ai'));
    }

    public function uniqueId(): string
    {
        return (string) $this->reportId;
    }

    public function handle(AiDailyReportService $reports, AiDailyReportSnapshotService $collector, AiDailyReportAnalystService $analyst): void
    {
        $reports->generate($this->reportId, $collector, $analyst);
    }

    public function failed(?Throwable $exception): void
    {
        $report = AiDailyReport::query()->find($this->reportId);
        if (! $report) {
            return;
        }

        $reports = app(AiDailyReportService::class);
        if (in_array($report->status, [AiDailyReport::STATUS_PENDING, AiDailyReport::STATUS_PROCESSING], true)) {
            $reports->recordFailure($report, 'generation_interrupted', $exception);
        }

        $reports->queueDelivery($report->refresh());
    }
}
