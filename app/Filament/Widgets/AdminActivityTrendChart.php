<?php

namespace App\Filament\Widgets;

use App\Models\Assignment;
use App\Models\ExamSubmission;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AdminActivityTrendChart extends ChartWidget
{
    protected ?string $heading = 'Activity Trends';

    protected ?string $description = 'Daily student registrations, exam submissions, and assignment activity.';

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'md' => 2,
        'xl' => 2,
    ];

    protected ?string $maxHeight = '330px';

    public ?string $timeRange = '7d';

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    protected function getFilters(): ?array
    {
        return [
            '7d' => 'Last 7 days',
            '14d' => 'Last 14 days',
            '30d' => 'Last 30 days',
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'font' => ['size' => 11],
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['drawBorder' => false],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
            'elements' => [
                'point' => ['radius' => 2, 'hoverRadius' => 4],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $days = match ($this->timeRange) {
            '14d' => 13,
            '30d' => 29,
            default => 6,
        };

        $dates = collect(range($days, 0))
            ->map(fn (int $daysAgo) => now()->subDays($daysAgo)->startOfDay());

        $labels = $dates->map(fn (CarbonInterface $date) => $date->format('M d'))->all();

        $registrationsRaw = User::query()
            ->where('is_admin', false)
            ->forWorkspace()
            ->whereDate('created_at', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $examSubmissionsRaw = ExamSubmission::query()
            ->whereHas('exam')
            ->whereDate('created_at', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $assignmentSubmissionsRaw = DB::table('assignment_user')
            ->whereIn('assignment_id', Assignment::query()->select('id'))
            ->where('submitted', true)
            ->whereDate('updated_at', '>=', now()->subDays($days)->toDateString())
            ->selectRaw('DATE(updated_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $registrations = $dates->map(
            fn (CarbonInterface $date) => (int) ($registrationsRaw[$date->toDateString()] ?? 0)
        )->all();
        $examSubmissions = $dates->map(
            fn (CarbonInterface $date) => (int) ($examSubmissionsRaw[$date->toDateString()] ?? 0)
        )->all();
        $assignmentSubmissions = $dates->map(
            fn (CarbonInterface $date) => (int) ($assignmentSubmissionsRaw[$date->toDateString()] ?? 0)
        )->all();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'New Students',
                    'data' => $registrations,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Exam Submissions',
                    'data' => $examSubmissions,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Assignment Submissions',
                    'data' => $assignmentSubmissions,
                    'borderColor' => '#38bdf8',
                    'backgroundColor' => 'rgba(56, 189, 248, 0.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
        ];
    }
}
