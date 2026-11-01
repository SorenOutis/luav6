<?php

namespace Database\Factories;

use App\Models\AiBot;
use App\Models\AiBotHandoff;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiBotHandoff>
 */
class AiBotHandoffFactory extends Factory
{
    protected $model = AiBotHandoff::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'from_bot_id' => AiBot::factory(),
            'requested_by_user_id' => User::factory(),
            'to_bot_id' => AiBot::factory(),
            'chat_session_id' => null,
            'parent_handoff_id' => null,
            'task' => fake()->sentence(12),
            'result' => null,
            'status' => AiBotHandoff::STATUS_QUEUED,
            'error' => null,
            'depth' => 0,
            'estimated_cost_micros' => null,
        ];
    }
}
