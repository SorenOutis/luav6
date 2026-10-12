<?php

namespace Database\Factories;

use App\Models\AiDailyReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiDailyReport> */
class AiDailyReportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'report_date' => fake()->unique()->dateTimeBetween('-10 years', '-1 day')->format('Y-m-d'),
            'timezone' => config('app.timezone'),
            'status' => AiDailyReport::STATUS_PENDING,
            'recipient_id' => User::factory()->superAdmin(),
            'workspace_ids' => [],
            'provider' => 'gemini',
            'max_cost_usd' => 0.25,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => AiDailyReport::STATUS_COMPLETED,
            'completed_at' => now(),
            'snapshot' => [
                'date' => '2026-10-10',
                'comparison_date' => '2026-10-09',
                'timezone' => config('app.timezone'),
                'workspace_ids' => [],
                'workspace_names' => [],
                'metrics' => [],
                'limitations' => [],
            ],
            'analysis' => [
                'summary' => 'No recorded activity in the selected workspaces.',
                'findings' => [],
                'recommendations' => [],
            ],
        ]);
    }
}
