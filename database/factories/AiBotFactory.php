<?php

namespace Database\Factories;

use App\Ai\Support\BotPolicy;
use App\Ai\Support\ToolRegistry;
use App\Models\AiBot;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AiBot>
 */
class AiBotFactory extends Factory
{
    protected $model = AiBot::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'workspace_id' => Workspace::factory(),
            'kind' => AiBot::KIND_SPECIALIST,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'name' => $name,
            'title' => fake()->sentence(4),
            'avatar' => null,
            'instructions' => null,
            'policy' => BotPolicy::normalize([
                'identity' => [
                    'role' => $name,
                    'mission' => fake()->sentence(10),
                ],
                'scope' => [
                    'owns' => ['Workspace support'],
                    'does_not_own' => ['Platform maintenance'],
                ],
            ], AiBot::KIND_SPECIALIST),
            'allowed_tools' => array_slice(ToolRegistry::readKeys(), 0, 4),
            'model' => null,
            'max_steps' => 8,
            'memory_scope' => AiBot::MEMORY_SESSION,
            'is_active' => true,
        ];
    }

    public function coordinator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => AiBot::KIND_COORDINATOR,
            'name' => 'Chief of Staff',
            'slug' => 'chief-of-staff-'.fake()->unique()->numerify('###'),
            'title' => 'Coordinates the bot team',
            'policy' => BotPolicy::normalize([
                'identity' => [
                    'role' => 'Chief of Staff',
                    'mission' => 'Route work to one owner per stage and summarize the result.',
                ],
                'delegation' => [
                    'mode' => BotPolicy::DELEGATION_SYNC_AND_ASYNC,
                    'max_depth' => BotPolicy::MAX_DELEGATION_DEPTH,
                ],
            ], AiBot::KIND_COORDINATOR),
            'allowed_tools' => array_slice(ToolRegistry::readKeys(), 0, 6),
            'memory_scope' => AiBot::MEMORY_BOT,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /** @param array<int, string> $keys */
    public function withTools(array $keys): static
    {
        return $this->state(fn (array $attributes): array => [
            'allowed_tools' => array_values(array_unique($keys)),
        ]);
    }
}
