<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SeasonProgressWidget extends BaseWidget
{
    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 12;

    protected ?string $heading = 'Season Progress';

    protected ?string $description = 'Current season performance and engagement metrics.';

    protected static bool $isLazy = true;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $service = app(AdminDashboardService::class);
        $data = $service->getSeasonStats();

        if (! $data) {
            return [
                Stat::make('No Active Season', '—')
                    ->description('Configure a season in the admin panel')
                    ->color('gray')
                    ->url('/admin/seasons'),
            ];
        }

        return [
            Stat::make('Season Progress', $data['progressPercent'].'%')
                ->description($data['daysRemaining'].' days remaining in '.$data['season']->name)
                ->descriptionIcon('heroicon-m-clock', IconPosition::Before)
                ->icon('heroicon-o-calendar')
                ->chart([$data['daysElapsed'], max(0, $data['totalDays'] - $data['daysElapsed'])])
                ->color('primary')
                ->url('/admin/seasons'),

            Stat::make('Active Students', number_format($data['activeStudents']))
                ->description('Avg '.number_format($data['avgXpPerStudent']).' XP per student')
                ->descriptionIcon('heroicon-m-users', IconPosition::Before)
                ->icon('heroicon-o-academic-cap')
                ->color('success'),

            Stat::make('Total XP Earned', number_format($data['totalXp']))
                ->description(number_format($data['totalPoints']).' points distributed')
                ->descriptionIcon('heroicon-m-bolt', IconPosition::Before)
                ->icon('heroicon-o-trophy')
                ->chart($data['weeklyTrend'])
                ->color('info'),

            Stat::make('Total Events', number_format($data['totalEvents']))
                ->description('Gamification actions recorded')
                ->descriptionIcon('heroicon-m-chart-bar', IconPosition::Before)
                ->icon('heroicon-o-rectangle-stack')
                ->color('warning'),
        ];
    }
}
