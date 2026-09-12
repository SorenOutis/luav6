<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardService;
use Filament\Widgets\ChartWidget;

class ExamPerformanceWidget extends ChartWidget
{
    protected ?string $heading = 'Exam Score Distribution';

    protected ?string $description = 'Score ranges from all published exams in the last 30 days.';

    protected ?string $pollingInterval = '120s';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 4,
    ];

    protected ?string $maxHeight = '340px';

    protected static bool $isLazy = true;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.parsed.y + " submissions"; }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0, 'stepSize' => 1],
                    'grid' => ['drawBorder' => false, 'color' => 'rgba(0,0,0,0.04)'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }

    protected function getData(): array
    {
        $service = app(AdminDashboardService::class);
        $buckets = $service->getExamPerformance();

        return [
            'labels' => array_keys($buckets),
            'datasets' => [
                [
                    'label' => 'Submissions',
                    'data' => array_values($buckets),
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.85)',
                        'rgba(249, 115, 22, 0.85)',
                        'rgba(234, 179, 8, 0.85)',
                        'rgba(132, 204, 22, 0.85)',
                        'rgba(34, 197, 94, 0.85)',
                    ],
                    'borderColor' => [
                        '#ef4444',
                        '#f97316',
                        '#eab308',
                        '#84cc16',
                        '#22c55e',
                    ],
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                    'barPercentage' => 0.65,
                    'categoryPercentage' => 0.85,
                ],
            ],
        ];
    }
}
