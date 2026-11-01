<?php

namespace Database\Factories;

use App\Models\AiBot;
use App\Models\AiBotPolicyRevision;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiBotPolicyRevision>
 */
class AiBotPolicyRevisionFactory extends Factory
{
    protected $model = AiBotPolicyRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'ai_bot_id' => AiBot::factory(),
            'version' => 1,
            'policy' => [],
            'allowed_tools' => [],
            'changed_by' => null,
            'change_summary' => 'Initial policy',
        ];
    }
}
