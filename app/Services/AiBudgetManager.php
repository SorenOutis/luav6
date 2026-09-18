<?php

namespace App\Services;

use App\Exceptions\AiBudgetExceededException;
use App\Models\AiBudgetEvent;
use App\Models\AiBudgetPeriod;
use App\Models\AiBudgetReservation;
use App\Models\AiUsageLog;
use App\Models\Setting;
use App\Support\WorkspaceContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiBudgetManager
{
    private const RESERVATION_TTL_MINUTES = 30;

    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly AiCostEstimator $costEstimator,
    ) {}

    public function enabled(): bool
    {
        return (string) Setting::get('ai_budget_enabled', '0') === '1';
    }

    public function reserve(
        string $provider,
        ?string $model,
        string $feature,
        int $inputTokens,
        int $maximumOutputTokens,
    ): ?AiBudgetReservation {
        if (! $this->enabled()) {
            return null;
        }

        $workspaceId = $this->workspaceContext->id();
        if (! $workspaceId) {
            return null;
        }

        $this->releaseExpired($workspaceId);

        $inputTokens = max(0, $inputTokens);
        $maximumOutputTokens = max(1, $maximumOutputTokens);
        $requestedTokens = $inputTokens + $maximumOutputTokens;
        $requestedCost = $this->costEstimator->estimateMicros(
            $provider,
            $model,
            $inputTokens,
            $maximumOutputTokens,
        );
        $limits = $this->limits();

        $decision = DB::transaction(function () use (
            $workspaceId,
            $provider,
            $model,
            $feature,
            $inputTokens,
            $maximumOutputTokens,
            $requestedTokens,
            $requestedCost,
            $limits,
        ): array {
            $periods = $this->lockCurrentPeriods($workspaceId, now());
            $blocked = $this->blockedBy($periods, $limits, $requestedTokens, $requestedCost);

            if ($blocked) {
                foreach ($periods as $period) {
                    $period->increment('blocked_count');
                }

                AiBudgetEvent::query()->create([
                    'workspace_id' => $workspaceId,
                    'user_id' => auth()->id(),
                    'feature' => $feature,
                    'provider' => $provider,
                    'model' => $model,
                    'event' => 'blocked',
                    'reason' => $blocked['period'].'_'.$blocked['metric'],
                    'context' => [
                        'limit' => $blocked['limit'],
                        'current' => $blocked['current'],
                        'requested' => $blocked['requested'],
                    ],
                ]);

                return ['blocked' => $blocked, 'reservation' => null];
            }

            foreach ($periods as $period) {
                $period->forceFill([
                    'reserved_tokens' => $period->reserved_tokens + $requestedTokens,
                    'reserved_cost_micros' => $period->reserved_cost_micros + $requestedCost,
                ])->save();
            }

            $reservation = AiBudgetReservation::query()->create([
                'workspace_id' => $workspaceId,
                'user_id' => auth()->id(),
                'feature' => Str::limit($feature, 32, ''),
                'provider' => Str::limit($provider, 80, ''),
                'model' => $model ? Str::limit($model, 191, '') : null,
                'reserved_input_tokens' => $inputTokens,
                'reserved_output_tokens' => $maximumOutputTokens,
                'reserved_cost_micros' => $requestedCost,
                'status' => AiBudgetReservation::STATUS_RESERVED,
                'expires_at' => now()->addMinutes(self::RESERVATION_TTL_MINUTES),
            ]);

            return ['blocked' => null, 'reservation' => $reservation];
        });

        if ($decision['blocked']) {
            $blocked = $decision['blocked'];
            throw new AiBudgetExceededException(
                $blocked['period'],
                $blocked['metric'],
                $blocked['limit'],
                $blocked['current'],
                $blocked['requested'],
            );
        }

        return $decision['reservation'];
    }

    public function settle(
        AiBudgetReservation $reservation,
        int $actualInputTokens,
        int $actualOutputTokens,
    ): void {
        $actualInputTokens = max(0, $actualInputTokens);
        $actualOutputTokens = max(0, $actualOutputTokens);

        DB::transaction(function () use ($reservation, $actualInputTokens, $actualOutputTokens): void {
            $locked = AiBudgetReservation::query()
                ->withoutGlobalScope('workspace')
                ->lockForUpdate()
                ->find($reservation->id);

            if (! $locked || $locked->status !== AiBudgetReservation::STATUS_RESERVED) {
                return;
            }

            $actualCost = $this->costEstimator->estimateMicros(
                $locked->provider,
                $locked->model,
                $actualInputTokens,
                $actualOutputTokens,
            );
            $periods = $this->lockCurrentPeriods($locked->workspace_id, $locked->created_at);
            $reservedTokens = $locked->reserved_input_tokens + $locked->reserved_output_tokens;

            foreach ($periods as $period) {
                $period->forceFill([
                    'reserved_tokens' => max(0, $period->reserved_tokens - $reservedTokens),
                    'reserved_cost_micros' => max(0, $period->reserved_cost_micros - $locked->reserved_cost_micros),
                    'used_input_tokens' => $period->used_input_tokens + $actualInputTokens,
                    'used_output_tokens' => $period->used_output_tokens + $actualOutputTokens,
                    'used_cost_micros' => $period->used_cost_micros + $actualCost,
                    'request_count' => $period->request_count + 1,
                ])->save();

                $this->emitWarningIfNeeded($period, $locked);
            }

            $locked->forceFill([
                'actual_input_tokens' => $actualInputTokens,
                'actual_output_tokens' => $actualOutputTokens,
                'actual_cost_micros' => $actualCost,
                'status' => AiBudgetReservation::STATUS_SETTLED,
                'settled_at' => now(),
            ])->save();

            AiUsageLog::query()->create([
                'workspace_id' => $locked->workspace_id,
                'ai_budget_reservation_id' => $locked->id,
                'date' => $locked->created_at->toDateString(),
                'provider' => $locked->provider,
                'model' => $locked->model,
                'source' => $locked->feature,
                'input_tokens' => $actualInputTokens,
                'output_tokens' => $actualOutputTokens,
                'neurons' => $locked->provider === 'cloudflare'
                    ? AiUsageTracker::estimateNeurons($locked->model, $actualInputTokens, $actualOutputTokens)
                    : null,
                'estimated_cost_micros' => $actualCost,
            ]);
        });
    }

    public function release(AiBudgetReservation $reservation, ?string $reason = null): void
    {
        DB::transaction(function () use ($reservation, $reason): void {
            $locked = AiBudgetReservation::query()
                ->withoutGlobalScope('workspace')
                ->lockForUpdate()
                ->find($reservation->id);

            if (! $locked || $locked->status !== AiBudgetReservation::STATUS_RESERVED) {
                return;
            }

            $periods = $this->lockCurrentPeriods($locked->workspace_id, $locked->created_at);
            $reservedTokens = $locked->reserved_input_tokens + $locked->reserved_output_tokens;

            foreach ($periods as $period) {
                $period->forceFill([
                    'reserved_tokens' => max(0, $period->reserved_tokens - $reservedTokens),
                    'reserved_cost_micros' => max(0, $period->reserved_cost_micros - $locked->reserved_cost_micros),
                ])->save();
            }

            $locked->forceFill([
                'status' => AiBudgetReservation::STATUS_RELEASED,
                'failure_reason' => $reason ? Str::limit($reason, 2000, '') : null,
                'released_at' => now(),
            ])->save();

            AiBudgetEvent::query()->create([
                'workspace_id' => $locked->workspace_id,
                'user_id' => $locked->user_id,
                'ai_budget_reservation_id' => $locked->id,
                'feature' => $locked->feature,
                'provider' => $locked->provider,
                'model' => $locked->model,
                'event' => 'released',
                'reason' => $reason
                    ? (str_contains(strtolower($reason), 'expired') ? 'reservation_expired' : 'provider_failure')
                    : 'cancelled',
                'context' => $reason ? ['message' => Str::limit($reason, 500, '')] : null,
            ]);
        });
    }

    public function recordFallback(
        string $feature,
        string $fromProvider,
        string $toProvider,
        string $reason,
    ): void {
        $workspaceId = $this->workspaceContext->id();
        if (! $workspaceId) {
            return;
        }

        AiBudgetEvent::query()->create([
            'workspace_id' => $workspaceId,
            'user_id' => auth()->id(),
            'feature' => $feature,
            'provider' => $toProvider,
            'event' => 'fallback',
            'reason' => Str::limit($reason, 64, ''),
            'context' => ['from' => $fromProvider, 'to' => $toProvider],
        ]);
    }

    /** @return array{daily_tokens: int, monthly_tokens: int, daily_cost_micros: int, monthly_cost_micros: int, warning_percent: int} */
    public function limits(): array
    {
        return [
            'daily_tokens' => max(0, (int) Setting::get('ai_budget_daily_tokens', 0)),
            'monthly_tokens' => max(0, (int) Setting::get('ai_budget_monthly_tokens', 0)),
            'daily_cost_micros' => $this->dollarsToMicros(Setting::get('ai_budget_daily_cost', 0)),
            'monthly_cost_micros' => $this->dollarsToMicros(Setting::get('ai_budget_monthly_cost', 0)),
            'warning_percent' => min(100, max(50, (int) Setting::get('ai_budget_warning_percent', 80))),
        ];
    }

    /**
     * @param  array<string, AiBudgetPeriod>  $periods
     * @param  array<string, int>  $limits
     * @return array{period: string, metric: string, limit: int, current: int, requested: int}|null
     */
    private function blockedBy(array $periods, array $limits, int $requestedTokens, int $requestedCost): ?array
    {
        foreach ([AiBudgetPeriod::TYPE_DAILY, AiBudgetPeriod::TYPE_MONTHLY] as $type) {
            $period = $periods[$type];
            $tokenLimit = $limits["{$type}_tokens"];
            $currentTokens = $period->used_input_tokens + $period->used_output_tokens + $period->reserved_tokens;

            if ($tokenLimit > 0 && $currentTokens + $requestedTokens > $tokenLimit) {
                return [
                    'period' => $type,
                    'metric' => 'tokens',
                    'limit' => $tokenLimit,
                    'current' => $currentTokens,
                    'requested' => $requestedTokens,
                ];
            }

            $costLimit = $limits["{$type}_cost_micros"];
            $currentCost = $period->used_cost_micros + $period->reserved_cost_micros;

            if ($costLimit > 0 && $currentCost + $requestedCost > $costLimit) {
                return [
                    'period' => $type,
                    'metric' => 'cost',
                    'limit' => $costLimit,
                    'current' => $currentCost,
                    'requested' => $requestedCost,
                ];
            }
        }

        return null;
    }

    /** @return array<string, AiBudgetPeriod> */
    private function lockCurrentPeriods(int $workspaceId, CarbonInterface $at): array
    {
        $starts = [
            AiBudgetPeriod::TYPE_DAILY => $at->copy()->startOfDay()->toDateString(),
            AiBudgetPeriod::TYPE_MONTHLY => $at->copy()->startOfMonth()->toDateString(),
        ];

        foreach ($starts as $type => $start) {
            DB::table('ai_budget_periods')->insertOrIgnore([
                'workspace_id' => $workspaceId,
                'period_type' => $type,
                'period_start' => $start,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $periods = AiBudgetPeriod::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->where(function ($query) use ($starts): void {
                foreach ($starts as $type => $start) {
                    $query->orWhere(function ($period) use ($type, $start): void {
                        $period->where('period_type', $type)->where('period_start', $start);
                    });
                }
            })
            ->orderBy('period_type')
            ->lockForUpdate()
            ->get()
            ->keyBy('period_type');

        // Usage logs are the durable source of truth. Reconcile while holding
        // both period locks so enabling budgets mid-period and prior disabled
        // usage are counted without racing concurrent reservations.
        foreach ($starts as $type => $start) {
            $existingUsage = AiUsageLog::query()
                ->withoutGlobalScope('workspace')
                ->where('workspace_id', $workspaceId)
                ->when(
                    $type === AiBudgetPeriod::TYPE_DAILY,
                    fn ($query) => $query->whereDate('date', $start),
                    fn ($query) => $query->whereDate('date', '>=', $start),
                )
                ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
                ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
                ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
                ->selectRaw('COUNT(*) as requests')
                ->first();
            $period = $periods->get($type);
            $period->forceFill([
                'used_input_tokens' => (int) ($existingUsage?->input_tokens ?? 0),
                'used_output_tokens' => (int) ($existingUsage?->output_tokens ?? 0),
                'used_cost_micros' => (int) ($existingUsage?->cost_micros ?? 0),
                'request_count' => (int) ($existingUsage?->requests ?? 0),
            ])->save();
        }

        return $periods->all();
    }

    private function emitWarningIfNeeded(AiBudgetPeriod $period, AiBudgetReservation $reservation): void
    {
        if ($period->warning_emitted_at) {
            return;
        }

        $limits = $this->limits();
        $tokenLimit = $limits["{$period->period_type}_tokens"];
        $costLimit = $limits["{$period->period_type}_cost_micros"];
        $tokens = $period->used_input_tokens + $period->used_output_tokens + $period->reserved_tokens;
        $cost = $period->used_cost_micros + $period->reserved_cost_micros;
        $tokenPercent = $tokenLimit > 0 ? ($tokens / $tokenLimit) * 100 : 0;
        $costPercent = $costLimit > 0 ? ($cost / $costLimit) * 100 : 0;
        $percent = max($tokenPercent, $costPercent);

        if ($percent < $limits['warning_percent']) {
            return;
        }

        $period->forceFill(['warning_emitted_at' => now()])->save();
        AiBudgetEvent::query()->create([
            'workspace_id' => $reservation->workspace_id,
            'user_id' => $reservation->user_id,
            'ai_budget_reservation_id' => $reservation->id,
            'feature' => $reservation->feature,
            'provider' => $reservation->provider,
            'model' => $reservation->model,
            'event' => 'warning',
            'reason' => $period->period_type,
            'context' => [
                'percent' => round($percent, 2),
                'threshold' => $limits['warning_percent'],
            ],
        ]);
    }

    private function releaseExpired(int $workspaceId): void
    {
        AiBudgetReservation::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->where('status', AiBudgetReservation::STATUS_RESERVED)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->each(fn (AiBudgetReservation $reservation) => $this->release($reservation, 'Reservation expired before the provider completed.'));
    }

    private function dollarsToMicros(mixed $value): int
    {
        $dollars = min(1000000, max(0, (float) $value));

        return (int) round($dollars * AiCostEstimator::MICROS_PER_DOLLAR);
    }
}
