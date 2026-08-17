<?php

namespace App\Services;

use App\Models\AiBudgetEvent;
use App\Models\AiBudgetPeriod;
use App\Models\AiUsageLog;
use App\Support\WorkspaceContext;

class AiBudgetReportingService
{
    public function __construct(
        private readonly AiBudgetManager $budgets,
        private readonly WorkspaceContext $workspaceContext,
    ) {}

    /** @return array<string, mixed> */
    public function dashboard(int $days = 14): array
    {
        $days = min(31, max(7, $days));
        $platformMode = auth()->user()?->isSuperAdmin() && ! $this->workspaceContext->isInspecting();
        $limits = $platformMode ? null : $this->budgets->limits();
        $today = now()->toDateString();
        $month = now()->startOfMonth()->toDateString();

        $dailyPeriod = $this->periodTotals(AiBudgetPeriod::TYPE_DAILY, $today);
        $monthlyPeriod = $this->periodTotals(AiBudgetPeriod::TYPE_MONTHLY, $month);

        return [
            'platformMode' => $platformMode,
            'budgetEnabled' => $platformMode ? null : $this->budgets->enabled(),
            'daily' => $this->periodPayload($dailyPeriod, $limits, 'daily'),
            'monthly' => $this->periodPayload($monthlyPeriod, $limits, 'monthly'),
            'features' => $this->featureBreakdown($month),
            'providers' => $this->providerBreakdown($month),
            'trend' => $this->dailyTrend($days),
            'events' => $this->recentEvents(),
            'workspaces' => $platformMode ? $this->workspaceBreakdown($month) : [],
            'estimatedNotice' => 'Token counts and provider costs are estimates based on application-observed text and configured model rates.',
        ];
    }

    /** @return array<string, int> */
    private function periodTotals(string $type, string $start): array
    {
        $row = AiBudgetPeriod::query()
            ->where('period_type', $type)
            ->where('period_start', $start)
            ->selectRaw('COALESCE(SUM(used_input_tokens), 0) as used_input_tokens')
            ->selectRaw('COALESCE(SUM(used_output_tokens), 0) as used_output_tokens')
            ->selectRaw('COALESCE(SUM(reserved_tokens), 0) as reserved_tokens')
            ->selectRaw('COALESCE(SUM(used_cost_micros), 0) as used_cost_micros')
            ->selectRaw('COALESCE(SUM(reserved_cost_micros), 0) as reserved_cost_micros')
            ->selectRaw('COALESCE(SUM(request_count), 0) as request_count')
            ->selectRaw('COALESCE(SUM(blocked_count), 0) as blocked_count')
            ->first();

        $usage = AiUsageLog::query()
            ->when(
                $type === AiBudgetPeriod::TYPE_DAILY,
                fn ($query) => $query->where('date', $start),
                fn ($query) => $query->where('date', '>=', $start),
            )
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
            ->selectRaw('COUNT(*) as requests')
            ->first();

        return [
            'used_input_tokens' => (int) ($usage?->input_tokens ?? 0),
            'used_output_tokens' => (int) ($usage?->output_tokens ?? 0),
            'reserved_tokens' => (int) ($row?->reserved_tokens ?? 0),
            'used_cost_micros' => (int) ($usage?->cost_micros ?? 0),
            'reserved_cost_micros' => (int) ($row?->reserved_cost_micros ?? 0),
            'request_count' => (int) ($usage?->requests ?? 0),
            'blocked_count' => (int) ($row?->blocked_count ?? 0),
        ];
    }

    /**
     * @param  array<string, int>  $period
     * @param  array<string, int>|null  $limits
     * @return array<string, int|float|null>
     */
    private function periodPayload(array $period, ?array $limits, string $type): array
    {
        $usedTokens = $period['used_input_tokens'] + $period['used_output_tokens'];
        $committedTokens = $usedTokens + $period['reserved_tokens'];
        $committedCost = $period['used_cost_micros'] + $period['reserved_cost_micros'];
        $tokenLimit = $limits["{$type}_tokens"] ?? null;
        $costLimit = $limits["{$type}_cost_micros"] ?? null;

        return [
            ...$period,
            'used_tokens' => $usedTokens,
            'committed_tokens' => $committedTokens,
            'committed_cost_micros' => $committedCost,
            'token_limit' => $tokenLimit,
            'cost_limit_micros' => $costLimit,
            'token_percent' => $tokenLimit
                ? round(min(100, ($committedTokens / $tokenLimit) * 100), 1)
                : null,
            'cost_percent' => $costLimit
                ? round(min(100, ($committedCost / $costLimit) * 100), 1)
                : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function featureBreakdown(string $from): array
    {
        return AiUsageLog::query()
            ->where('date', '>=', $from)
            ->select('source')
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('COALESCE(SUM(input_tokens), 0) as input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) as output_tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
            ->groupBy('source')
            ->orderByDesc('requests')
            ->get()
            ->map(fn ($row): array => [
                'feature' => $row->source,
                'requests' => (int) $row->requests,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cost_micros' => (int) $row->cost_micros,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function providerBreakdown(string $from): array
    {
        return AiUsageLog::query()
            ->where('date', '>=', $from)
            ->select(['provider', 'model'])
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
            ->groupBy('provider', 'model')
            ->orderByDesc('requests')
            ->limit(20)
            ->get()
            ->map(fn ($row): array => [
                'provider' => $row->provider,
                'model' => $row->model,
                'requests' => (int) $row->requests,
                'tokens' => (int) $row->tokens,
                'cost_micros' => (int) $row->cost_micros,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function dailyTrend(int $days): array
    {
        $start = now()->startOfDay()->subDays($days - 1);
        $rows = AiUsageLog::query()
            ->where('date', '>=', $start->toDateString())
            ->select('date')
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
            ->selectRaw('COUNT(*) as requests')
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row): string => $row->date->toDateString());

        $trend = [];
        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = now()->startOfDay()->subDays($offset)->toDateString();
            $row = $rows->get($date);
            $trend[] = [
                'date' => $date,
                'tokens' => (int) ($row?->tokens ?? 0),
                'cost_micros' => (int) ($row?->cost_micros ?? 0),
                'requests' => (int) ($row?->requests ?? 0),
            ];
        }

        return $trend;
    }

    /** @return array<int, array<string, mixed>> */
    private function recentEvents(): array
    {
        return AiBudgetEvent::query()
            ->with('workspace:id,name')
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (AiBudgetEvent $event): array => [
                'id' => $event->id,
                'workspace' => $event->workspace?->name,
                'feature' => $event->feature,
                'provider' => $event->provider,
                'event' => $event->event,
                'reason' => $event->reason,
                'context' => $event->context,
                'created_at' => $event->created_at?->diffForHumans(),
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function workspaceBreakdown(string $from): array
    {
        return AiUsageLog::query()
            ->withoutGlobalScope('workspace')
            ->leftJoin('workspaces', 'workspaces.id', '=', 'ai_usage_logs.workspace_id')
            ->where('date', '>=', $from)
            ->whereNotNull('ai_usage_logs.workspace_id')
            ->select(['ai_usage_logs.workspace_id', 'workspaces.name'])
            ->selectRaw('COUNT(*) as requests')
            ->selectRaw('COALESCE(SUM(input_tokens + output_tokens), 0) as tokens')
            ->selectRaw('COALESCE(SUM(estimated_cost_micros), 0) as cost_micros')
            ->groupBy('ai_usage_logs.workspace_id', 'workspaces.name')
            ->orderByDesc('tokens')
            ->limit(20)
            ->get()
            ->map(fn ($row): array => [
                'workspace_id' => (int) $row->workspace_id,
                'workspace' => $row->name ?? 'Unknown workspace',
                'requests' => (int) $row->requests,
                'tokens' => (int) $row->tokens,
                'cost_micros' => (int) $row->cost_micros,
            ])
            ->all();
    }
}
