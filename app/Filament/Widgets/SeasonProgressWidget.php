<?php

namespace App\Filament\Widgets;

use App\Models\GamificationHistory;
use App\Models\Season;
use App\Models\SeasonProgress;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SeasonProgressWidget extends BaseWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Season Progress';

    protected ?string $description = 'Current season performance and engagement metrics.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $activeSeason = Season::query()->where('is_active', true)->first();

        if (! $activeSeason) {
            return [
                Stat::make('No Active Season', '—')
                    ->description('Configure a season in the admin panel')
                    ->color('gray'),
            ];
        }

        $daysElapsed = max(1, $activeSeason->start_date?->diffInDays(now()) ?? 0);
        $totalDays = $activeSeason->start_date ? $activeSeason->start_date->diffInDays($activeSeason->end_date) : 1;
        $daysRemaining = max(0, $activeSeason->end_date?->diffInDays(now()) ?? 0);
        $progressPercent = $totalDays > 0 ? round(($daysElapsed / $totalDays) * 100, 1) : 0;

        // Total XP earned by all students this season
        $totalSeasonXp = SeasonProgress::query()
            ->whereHas('season', fn ($q) => $q->where('is_active', true))
            ->sum('exp');

        // Total points earned
        $totalSeasonPoints = SeasonProgress::query()
            ->whereHas('season', fn ($q) => $q->where('is_active', true))
            ->sum('points');

        // Active students this season (users with season progress > 0)
        $activeStudents = SeasonProgress::query()
            ->whereHas('season', fn ($q) => $q->where('is_active', true))
            ->where('exp', '>', 0)
            ->distinct('user_id')
            ->count('user_id');

        // Gamification events this season (count of history entries)
        $totalEvents = GamificationHistory::query()
            ->where('season_id', $activeSeason->id)
            ->count();

        // Avg XP per active student
        $avgXpPerStudent = $activeStudents > 0 ? round($totalSeasonXp / $activeStudents, 0) : 0;

        return [
            Stat::make('Season Progress', $progressPercent.'%')
                ->description($daysRemaining.' days remaining')
                ->descriptionIcon('heroicon-m-clock', \Filament\Support\Enums\IconPosition::Before)
                ->icon('heroicon-o-calendar')
                ->chart([$daysElapsed, max(0, $totalDays - $daysElapsed)])
                ->color('primary'),

            Stat::make('Active Students', number_format($activeStudents))
                ->description('Avg '.number_format($avgXpPerStudent).' XP per student')
                ->descriptionIcon('heroicon-m-users', \Filament\Support\Enums\IconPosition::Before)
                ->icon('heroicon-o-academic-cap')
                ->color('success'),

            Stat::make('Total XP Earned', number_format($totalSeasonXp))
                ->description(number_format($totalSeasonPoints).' points distributed')
                ->descriptionIcon('heroicon-m-bolt', \Filament\Support\Enums\IconPosition::Before)
                ->icon('heroicon-o-trophy')
                ->chart($this->weeklyXpTrend($activeSeason->id))
                ->color('info'),

            Stat::make('Total Events', number_format($totalEvents))
                ->description('Gamification actions recorded')
                ->descriptionIcon('heroicon-m-chart-bar', \Filament\Support\Enums\IconPosition::Before)
                ->icon('heroicon-o-rectangle-stack')
                ->color('warning'),
        ];
    }

    /**
     * @return array<float>
     */
    private function weeklyXpTrend(int $seasonId): array
    {
        $raw = GamificationHistory::query()
            ->where('season_id', $seasonId)
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('DATE(created_at) as day, SUM(amount_xp) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(6, 0))
            ->map(fn (int $daysAgo): float => (float) ($raw[now()->subDays($daysAgo)->toDateString()] ?? 0))
            ->all();
    }
}
