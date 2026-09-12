<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminAnalyticsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 12;

    protected ?string $heading = 'Operational Pulse';

    protected ?string $description = 'Live signals for growth, activity, submissions, and moderation.';

    protected static bool $isLazy = false;

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $service = app(AdminDashboardService::class);
        $overview = $service->getOverviewStats();

        $totalStudents = $overview['totalStudents'];
        $activeToday = $overview['activeToday'];
        $bannedStudents = $overview['bannedStudents'];
        $examSubmissions7d = $overview['examSubmissions7d'];
        $totalAssignmentTargets = $overview['totalAssignmentTargets'];
        $submittedAssignments = $overview['submittedAssignmentTargets'];
        $assignmentSubmissionRate = $overview['assignmentSubmissionRate'];
        $totalXpAll = $overview['totalXp'];
        $gamesPlayedWeek = $overview['gamesWeek'];
        $anonymousMessagesWeek = $overview['anonymousMessagesWeek'];
        $studentsLast7Days = $overview['newThisWeek'];
        $studentsPrev7Days = $overview['newPrevWeek'];

        $studentGrowthDescription = $this->formatGrowthDescription($studentsLast7Days, $studentsPrev7Days);

        // Charts from cached service
        $dailyRegs = $service->getDailyRegistrations(6);
        $dailyAssign = $service->getDailyAssignmentSubmissions(6);
        $dailyXp = $service->getDailyXpDistribution(6);

        return [
            Stat::make('Total Students', number_format($totalStudents))
                ->description($studentGrowthDescription)
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::Before)
                ->icon('heroicon-o-users')
                ->chart($dailyRegs)
                ->color('primary')
                ->url('/admin/users')
                ->extraAttributes(['class' => 'cursor-pointer']),

            Stat::make('Active Today', number_format($activeToday))
                ->description($totalStudents > 0 ? round(($activeToday / $totalStudents) * 100, 1).'% of total students' : 'No students yet')
                ->descriptionIcon('heroicon-m-bolt', IconPosition::Before)
                ->icon('heroicon-o-sparkles')
                ->color('success'),

            Stat::make('Submission Rate', $assignmentSubmissionRate.'%')
                ->description(number_format($submittedAssignments).' / '.number_format($totalAssignmentTargets).' assigned')
                ->descriptionIcon('heroicon-m-check-badge', IconPosition::Before)
                ->icon('heroicon-o-clipboard-document-check')
                ->chart($dailyAssign)
                ->color('info')
                ->url('/admin/assignments'),

            Stat::make('Total XP Earned', number_format($totalXpAll))
                ->description(number_format($gamesPlayedWeek).' game plays this week')
                ->descriptionIcon('heroicon-m-trophy', IconPosition::Before)
                ->icon('heroicon-o-fire')
                ->chart($dailyXp)
                ->color('warning'),

            Stat::make('Banned Students', number_format($bannedStudents))
                ->description(number_format($examSubmissions7d).' exam submissions in last 7 days')
                ->descriptionIcon('heroicon-m-no-symbol', IconPosition::Before)
                ->icon('heroicon-o-shield-exclamation')
                ->color($bannedStudents > 0 ? 'danger' : 'gray')
                ->url('/admin/users?tableFilters[banned][value]=1'),

            Stat::make('Community Activity', number_format($anonymousMessagesWeek))
                ->description('Anonymous messages this week')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right', IconPosition::Before)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('secondary')
                ->url('/admin/anonymous-messages'),
        ];
    }

    private function formatGrowthDescription(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0
                ? '+'.$current.' new this week'
                : 'No new students this week';
        }

        $change = (($current - $previous) / $previous) * 100;
        $prefix = $change >= 0 ? '+' : '';

        return $prefix.round($change, 1).'% vs previous 7 days';
    }
}
