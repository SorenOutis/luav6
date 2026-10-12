<?php

namespace App\Services;

use App\Jobs\DeliverAiDailyReport;
use App\Jobs\GenerateAiDailyReport;
use App\Models\AiDailyReport;
use App\Models\User;
use App\Support\AiDailyReportSettings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class AiDailyReportService
{
    public function dispatch(?string $date = null): ?AiDailyReport
    {
        $timezone = (string) config('app.timezone');
        $now = CarbonImmutable::now($timezone);
        $settings = AiDailyReportSettings::state();
        $time = (string) ($settings['time'] ?? config('ai_reports.time', '00:15'));

        if (! preg_match('/\A(?:[01][0-9]|2[0-3]):[0-5][0-9]\z/', $time)) {
            throw new RuntimeException('AI_DAILY_REPORT_TIME must use 24-hour HH:MM format.');
        }

        if ($date === null && $now->format('H:i') < $time) {
            return null;
        }

        $date ??= $now->subDay()->toDateString();
        Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d']])->validate();

        if ($date >= $now->toDateString()) {
            throw ValidationException::withMessages(['date' => 'Reports require a complete past calendar day.']);
        }

        if (! $settings['enabled'] || $settings['workspace_ids'] === [] || ! $this->recipient($settings['recipient_id'])) {
            return null;
        }

        $workspaceIds = $settings['workspace_ids'];
        sort($workspaceIds, SORT_NUMERIC);
        $report = AiDailyReport::query()->firstOrCreate(['report_date' => $date], [
            'timezone' => $timezone,
            'recipient_id' => $settings['recipient_id'],
            'workspace_ids' => $workspaceIds,
            'provider' => $settings['provider'],
            'max_cost_usd' => $settings['max_cost_usd'],
        ]);

        if ($report->status === AiDailyReport::STATUS_PROCESSING && $report->started_at?->lt(now()->subMinutes(10))) {
            // Never repeat a possibly billed request after an interrupted worker.
            AiDailyReport::query()->whereKey($report->id)
                ->where('status', AiDailyReport::STATUS_PROCESSING)
                ->where('started_at', '<', now()->subMinutes(10))
                ->update([
                    'status' => $report->snapshot === null ? AiDailyReport::STATUS_FAILED : AiDailyReport::STATUS_INCOMPLETE,
                    'error_code' => 'generation_interrupted',
                    'completed_at' => now(),
                ]);
            $report->refresh();
        }

        if ($report->isReadyForDelivery()) {
            $this->queueDelivery($report);

            return $report;
        }

        $claimed = AiDailyReport::query()->whereKey($report->id)
            ->where('status', AiDailyReport::STATUS_PENDING)
            ->where(fn ($query) => $query->whereNull('generation_queued_at')
                ->orWhere('generation_queued_at', '<', now()->subMinutes(10)))
            ->update(['generation_queued_at' => now()]);

        if ($claimed) {
            try {
                GenerateAiDailyReport::dispatch($report->id)->afterCommit();
            } catch (Throwable $exception) {
                AiDailyReport::query()->whereKey($report->id)->where('status', AiDailyReport::STATUS_PENDING)
                    ->update(['generation_queued_at' => null]);
                throw $exception;
            }
        }

        return $report;
    }

    public function generate(int $reportId, AiDailyReportSnapshotService $collector, AiDailyReportAnalystService $analyst): void
    {
        $report = AiDailyReport::query()->find($reportId);
        if (! $report) {
            return;
        }

        if ($report->isReadyForDelivery()) {
            $this->queueDelivery($report);

            return;
        }

        if ($report->status !== AiDailyReport::STATUS_PENDING) {
            return;
        }

        if (! $this->isAllowed($report)) {
            $report->update(['status' => AiDailyReport::STATUS_CANCELLED, 'error_code' => 'settings_changed']);

            return;
        }

        if (! AiDailyReport::query()->whereKey($report->id)->where('status', AiDailyReport::STATUS_PENDING)
            ->update(['status' => AiDailyReport::STATUS_PROCESSING, 'started_at' => now()])) {
            return;
        }

        $report->refresh();

        try {
            $snapshot = $collector->collect($report->workspace_ids, $report->report_date->toDateString(), $report->timezone);
        } catch (Throwable $exception) {
            $this->recordFailure($report, 'metrics_unavailable', $exception);
            $this->queueDelivery($report->refresh());

            return;
        }

        $report->update(['snapshot' => $snapshot]);

        try {
            $result = $analyst->analyze(
                $snapshot,
                $report->provider,
                $report->max_cost_usd,
                fn (array $usage): bool => $report->update($usage),
            );
        } catch (Throwable $exception) {
            $this->recordFailure($report, 'analysis_unavailable', $exception);
            $this->queueDelivery($report->refresh());

            return;
        }

        $report->update([
            ...$result,
            'status' => AiDailyReport::STATUS_COMPLETED,
            'error_code' => null,
            'completed_at' => now(),
        ]);
        $this->queueDelivery($report);
    }

    public function queueDelivery(AiDailyReport $report): void
    {
        if (! $report->isReadyForDelivery() || $report->email_sent_at !== null || ! $this->isAllowed($report)) {
            return;
        }

        // Automatic mail retries are bounded. Operators can use queue:retry for
        // exhausted deliveries; schedule ticks must not restart them forever.
        if ($report->delivery_failed_at !== null) {
            return;
        }

        $claimed = AiDailyReport::query()->whereKey($report->id)->whereNull('email_sent_at')
            ->where(fn ($query) => $query->whereNull('delivery_queued_at')
                ->orWhere('delivery_queued_at', '<', now()->subMinutes(10)))
            ->update(['delivery_queued_at' => now()]);

        if ($claimed) {
            try {
                DeliverAiDailyReport::dispatch($report->id)->afterCommit();
            } catch (Throwable $exception) {
                AiDailyReport::query()->whereKey($report->id)->whereNull('email_sent_at')
                    ->update(['delivery_queued_at' => null]);
                throw $exception;
            }
        }
    }

    public function isAllowed(AiDailyReport $report): bool
    {
        $settings = AiDailyReportSettings::state();
        $selectedIds = $settings['workspace_ids'];
        $reportIds = $report->workspace_ids;
        sort($selectedIds, SORT_NUMERIC);
        sort($reportIds, SORT_NUMERIC);

        return $settings['enabled']
            && $settings['recipient_id'] === $report->recipient_id
            && $selectedIds === $reportIds
            && $settings['provider'] === $report->provider
            && abs($settings['max_cost_usd'] - $report->max_cost_usd) < 0.0000001
            && $this->recipient($report->recipient_id) !== null;
    }

    public function recipient(?int $userId): ?User
    {
        if ($userId === null) {
            return null;
        }

        $user = User::query()->whereKey($userId)
            ->where(fn ($query) => $query
                ->where('is_super_admin', true)
                ->orWhere(fn ($q) => $q->where('is_admin', true)->where('is_super_admin', true))
            )
            ->first();

        return $user && filter_var($user->email, FILTER_VALIDATE_EMAIL) ? $user : null;
    }

    public function recordFailure(AiDailyReport $report, string $code, ?Throwable $exception): void
    {
        $report->update([
            'status' => $report->snapshot === null ? AiDailyReport::STATUS_FAILED : AiDailyReport::STATUS_INCOMPLETE,
            'error_code' => $code,
            'completed_at' => now(),
        ]);

        Log::warning('Nightly AI report generation did not complete.', [
            'report_id' => $report->id,
            'error_code' => $code,
            'exception_type' => $exception ? $exception::class : null,
        ]);
    }
}
