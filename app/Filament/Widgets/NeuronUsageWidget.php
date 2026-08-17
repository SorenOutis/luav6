<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AiUsageDashboard;
use App\Services\AiBudgetReportingService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Workspace-scoped AI budget and usage overview. */
class NeuronUsageWidget extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Workspace AI Usage & Budget';

    protected ?string $description = 'Atomic token reservations plus estimated provider spend. Open the detailed dashboard for feature, provider, and event breakdowns.';

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $report = app(AiBudgetReportingService::class)->dashboard();
        $daily = $report['daily'];
        $monthly = $report['monthly'];
        $trend = array_map(fn (array $day): int => $day['tokens'], $report['trend']);
        $featureCalls = collect($report['features'])->mapWithKeys(
            fn (array $feature): array => [$feature['feature'] => $feature['requests']],
        );
        $tokenPercent = $daily['token_percent'];
        $costPercent = $daily['cost_percent'];
        $highestPercent = max((float) ($tokenPercent ?? 0), (float) ($costPercent ?? 0));
        $color = $highestPercent >= 90 ? 'danger' : ($highestPercent >= 70 ? 'warning' : 'success');
        $url = AiUsageDashboard::getUrl();

        return [
            Stat::make(
                $report['platformMode'] ? 'Platform Tokens Today' : 'Tokens Today',
                number_format($daily['committed_tokens'])
                    .($daily['token_limit'] ? ' / '.number_format($daily['token_limit']) : ''),
            )
                ->description(number_format($daily['reserved_tokens']).' reserved · '.number_format($daily['blocked_count']).' blocked')
                ->descriptionIcon('heroicon-m-scale', IconPosition::Before)
                ->icon('heroicon-o-sparkles')
                ->chart($trend)
                ->color($color)
                ->url($url),

            Stat::make(
                'Estimated Cost Today',
                '$'.number_format($daily['committed_cost_micros'] / 1_000_000, 4)
                    .($daily['cost_limit_micros'] ? ' / $'.number_format($daily['cost_limit_micros'] / 1_000_000, 2) : ''),
            )
                ->description('$'.number_format($monthly['committed_cost_micros'] / 1_000_000, 4).' estimated this month')
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->icon('heroicon-o-currency-dollar')
                ->color($color)
                ->url($url),

            Stat::make('AI Calls This Month', number_format(array_sum($featureCalls->all())))
                ->description(sprintf(
                    '%s chat · %s grading · %s generation',
                    number_format((int) ($featureCalls['chat'] ?? 0)),
                    number_format((int) ($featureCalls['grading'] ?? 0)),
                    number_format((int) ($featureCalls['generation'] ?? 0)),
                ))
                ->descriptionIcon('heroicon-m-chart-bar', IconPosition::Before)
                ->icon('heroicon-o-command-line')
                ->color('info')
                ->url($url),
        ];
    }
}
