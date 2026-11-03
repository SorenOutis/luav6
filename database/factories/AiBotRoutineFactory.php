<?php

namespace Database\Factories;

use App\Models\AiBot;
use App\Models\AiBotRoutine;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiBotRoutine>
 */
class AiBotRoutineFactory extends Factory
{
    protected $model = AiBotRoutine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'bot_id' => AiBot::factory(),
            'name' => fake()->words(3, true),
            'cron' => '0 7 * * *',
            'prompt' => fake()->sentence(12),
            'is_active' => true,
            'last_run_at' => null,
            'last_status' => null,
            'last_error' => null,
        ];
    }
}
