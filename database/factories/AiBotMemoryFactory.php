<?php

namespace Database\Factories;

use App\Models\AiBot;
use App\Models\AiBotMemory;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiBotMemory>
 */
class AiBotMemoryFactory extends Factory
{
    protected $model = AiBotMemory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'bot_id' => AiBot::factory(),
            'user_id' => null,
            'content' => fake()->sentence(14),
            'source' => AiBotMemory::SOURCE_MANUAL,
            'importance' => 2,
            'created_by' => null,
        ];
    }
}
