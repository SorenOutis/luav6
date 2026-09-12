<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\ChartWidget;

class AdminActivityTrendChart extends ChartWidget
{
    protected ?string $heading = 'Activity Trends';

    protected ?string $description = 'Daily student registrations, exam submissions, and assignment activity.';

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 8,
    ];

    protected ?string $maxHeight = '340px';

    public ?string $timeRange = '7d';

    protected static bool $isLazy = true;

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
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'font' => ['size' => 11],
                        'usePointStyle' => true,
                        'padding' => 16,
                    ],
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                    'grid' => ['drawBorder' => false, 'color' => 'rgba(0,0,0,0.04)'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
            'elements' => [
                'point' => ['radius' => 0, 'hoverRadius' => 4, 'hitRadius' => 8],
                'line' => ['borderWidth' => 2],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $service = app(AdminDashboardService::class);
        $trend = $service->getTrendData($this->filter ?? $this->timeRange ?? '7d');

        return [
            'labels' => $trend['labels'],
            'datasets' => [
                [
                    'label' => 'New Students',
                    'data' => $trend['registrations'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.12)',
                    'tension' => 0.4,
                    'fill' => true,
                ],
                [
                    'label' => 'Exam Submissions',
                    'data' => $trend['examSubmissions'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.10)',
                    'tension' => 0.4,
                    'fill' => true,
                ],
                [
                    'label' => 'Assignment Submissions',
                    'data' => $trend['assignmentSubmissions'],
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.10)',
                    'tension' => 0.4,
                    'fill' => true,
                ],
            ],
        ];
    }
}
