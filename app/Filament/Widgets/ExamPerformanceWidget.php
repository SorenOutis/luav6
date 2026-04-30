<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ExamPerformanceWidget extends ChartWidget
{
    protected ?string $heading = 'Exam Score Distribution';

    protected ?string $description = 'Score ranges from all published exams in the last 30 days.';

    protected ?string $pollingInterval = '60s';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    protected ?string $maxHeight = '330px';

    protected function getType(): string
    {
        return 'bar';
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
                    'display' => false,
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => 'function(context) { return context.parsed.y . " students"; }',
                    ],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0, 'stepSize' => 1],
                    'grid' => ['drawBorder' => false],
                ],
                'x' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $cutoff = now()->subDays(30)->toDateString();

        // Get best score per user per exam in last 30 days
        $bestScores = DB::table('exam_submissions')
            ->select('user_id', 'exam_id', DB::raw('MAX(CAST(score AS DECIMAL(10,2))) as best_score'))
            ->where('created_at', '>=', $cutoff)
            ->whereNotNull('score')
            ->groupBy('user_id', 'exam_id')
            ->get();

        // Bucket the scores
        $buckets = ['0–20' => 0, '21–40' => 0, '41–60' => 0, '61–80' => 0, '81–100' => 0];

        foreach ($bestScores as $row) {
            $score = (float) $row->best_score;
            if ($score <= 20) {
                $buckets['0–20']++;
            } elseif ($score <= 40) {
                $buckets['21–40']++;
            } elseif ($score <= 60) {
                $buckets['41–60']++;
            } elseif ($score <= 80) {
                $buckets['61–80']++;
            } else {
                $buckets['81–100']++;
            }
        }

        $labels = array_keys($buckets);
        $data = array_values($buckets);

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Students',
                    'data' => $data,
                    'backgroundColor' => [
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(249, 115, 22, 0.8)',
                        'rgba(234, 179, 8, 0.8)',
                        'rgba(132, 204, 22, 0.8)',
                        'rgba(34, 197, 94, 0.8)',
                    ],
                    'borderColor' => [
                        '#ef4444',
                        '#f97316',
                        '#eab308',
                        '#84cc16',
                        '#22c55e',
                    ],
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'barPercentage' => 0.7,
                ],
            ],
        ];
    }
}
