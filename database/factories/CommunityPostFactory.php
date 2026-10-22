<?php

namespace Database\Factories;

use App\Models\CommunityPost;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityPost>
 */
class CommunityPostFactory extends Factory
{
    protected $model = CommunityPost::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'section_id' => Section::factory(),
            'content' => fake()->paragraph(),
            'is_anonymous' => false,
            'likes_count' => 0,
            'comments_count' => 0,
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn () => [
            'is_anonymous' => true,
        ]);
    }
}
