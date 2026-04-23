<?php

namespace App\Filament\Widgets;

use App\Models\ExamSubmission;
use App\Models\User;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'System Analytics';

    protected ?string $description = 'High-level metrics for student growth and platform activity.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totalStudents = User::query()->where('is_admin', false)->count();
        $activeToday = User::query()
            ->where('is_admin', false)
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->startOfDay())
            ->count();
        $bannedStudents = User::query()
            ->where('is_admin', false)
            ->where('is_banned', true)
            ->count();
        $examSubmissions7d = ExamSubmission::query()
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->count();

        $totalAssignmentTargets = DB::table('assignment_user')->count();
        $submittedAssignments = DB::table('assignment_user')->where('submitted', true)->count();
        $assignmentSubmissionRate = $totalAssignmentTargets > 0
            ? round(($submittedAssignments / $totalAssignmentTargets) * 100, 1)
            : 0.0;

        $studentsLast7Days = User::query()
            ->where('is_admin', false)
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->count();
        $studentsPrev7Days = User::query()
            ->where('is_admin', false)
            ->whereBetween('created_at', [now()->subDays(14)->startOfDay(), now()->subDays(7)->startOfDay()])
            ->count();

        $studentGrowthDescription = $this->formatGrowthDescription($studentsLast7Days, $studentsPrev7Days);

        return [
            Stat::make('Total Students', number_format($totalStudents))
                ->description($studentGrowthDescription)
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::Before)
                ->color('primary'),

            Stat::make('Active Today', number_format($activeToday))
                ->description('Students who logged in today')
                ->descriptionIcon('heroicon-m-bolt', IconPosition::Before)
                ->color('success'),

            Stat::make('Submission Rate', $assignmentSubmissionRate.'%')
                ->description(number_format($submittedAssignments).' submitted / '.number_format($totalAssignmentTargets).' assigned')
                ->descriptionIcon('heroicon-m-check-badge', IconPosition::Before)
                ->color('info'),

            Stat::make('Banned Students', number_format($bannedStudents))
                ->description(number_format($examSubmissions7d).' exam submissions in last 7 days')
                ->descriptionIcon('heroicon-m-no-symbol', IconPosition::Before)
                ->color($bannedStudents > 0 ? 'danger' : 'gray'),
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
