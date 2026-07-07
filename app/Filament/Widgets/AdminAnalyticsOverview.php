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

    protected ?string $heading = 'Operational Pulse';

    protected ?string $description = 'Live signals for growth, activity, submissions, and moderation.';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $totalStudents = User::query()->where('is_admin', false)->forWorkspace()->count();
        $activeToday = User::query()
            ->where('is_admin', false)
            ->forWorkspace()
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->startOfDay())
            ->count();
        $bannedStudents = User::query()
            ->where('is_admin', false)
            ->forWorkspace()
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
            ->forWorkspace()
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->count();
        $studentsPrev7Days = User::query()
            ->where('is_admin', false)
            ->forWorkspace()
            ->whereBetween('created_at', [now()->subDays(14)->startOfDay(), now()->subDays(7)->startOfDay()])
            ->count();

        // Total XP across all students
        $totalXpAll = User::query()->where('is_admin', false)->forWorkspace()->sum('exp');

        // Games played this week (tower defense runs)
        $gamesPlayedWeek = DB::table('td_runs')
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->count();

        // Anonymous messages this week (engagement signal)
        $anonymousMessagesWeek = DB::table('anonymous_messages')
            ->where('created_at', '>=', now()->subDays(7)->startOfDay())
            ->count();

        $studentGrowthDescription = $this->formatGrowthDescription($studentsLast7Days, $studentsPrev7Days);

        return [
            Stat::make('Total Students', number_format($totalStudents))
                ->description($studentGrowthDescription)
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::Before)
                ->icon('heroicon-o-users')
                ->chart($this->dailyStudentRegistrations())
                ->color('primary'),

            Stat::make('Active Today', number_format($activeToday))
                ->description('Students who logged in today')
                ->descriptionIcon('heroicon-m-bolt', IconPosition::Before)
                ->icon('heroicon-o-sparkles')
                ->color('success'),

            Stat::make('Submission Rate', $assignmentSubmissionRate.'%')
                ->description(number_format($submittedAssignments).' submitted / '.number_format($totalAssignmentTargets).' assigned')
                ->descriptionIcon('heroicon-m-check-badge', IconPosition::Before)
                ->icon('heroicon-o-clipboard-document-check')
                ->chart($this->dailyAssignmentSubmissions())
                ->color('info'),

            Stat::make('Total XP Earned', number_format($totalXpAll))
                ->description(number_format($gamesPlayedWeek).' game plays this week')
                ->descriptionIcon('heroicon-m-trophy', IconPosition::Before)
                ->icon('heroicon-o-fire')
                ->chart($this->weeklyXpDistribution())
                ->color('warning'),

            Stat::make('Banned Students', number_format($bannedStudents))
                ->description(number_format($examSubmissions7d).' exam submissions in last 7 days')
                ->descriptionIcon('heroicon-m-no-symbol', IconPosition::Before)
                ->icon('heroicon-o-shield-exclamation')
                ->color($bannedStudents > 0 ? 'danger' : 'gray'),

            Stat::make('Community Activity', number_format($anonymousMessagesWeek))
                ->description('Messages this week')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right', IconPosition::Before)
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('secondary'),
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

    /**
     * @return array<float>
     */
    private function dailyStudentRegistrations(): array
    {
        $raw = User::query()
            ->where('is_admin', false)
            ->forWorkspace()
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(6, 0))
            ->map(fn (int $daysAgo): float => (float) ($raw[now()->subDays($daysAgo)->toDateString()] ?? 0))
            ->all();
    }

    /**
     * @return array<float>
     */
    private function dailyAssignmentSubmissions(): array
    {
        $raw = DB::table('assignment_user')
            ->where('submitted', true)
            ->whereDate('updated_at', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('DATE(updated_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(6, 0))
            ->map(fn (int $daysAgo): float => (float) ($raw[now()->subDays($daysAgo)->toDateString()] ?? 0))
            ->all();
    }

    /**
     * @return array<float>
     */
    private function weeklyXpDistribution(): array
    {
        $raw = DB::table('gamification_histories')
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('DATE(created_at) as day, SUM(amount_xp) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(6, 0))
            ->map(fn (int $daysAgo): float => (float) ($raw[now()->subDays($daysAgo)->toDateString()] ?? 0))
            ->all();
    }
}
