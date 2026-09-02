<?php

namespace Database\Factories;

use App\Models\LearningMaterialCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningMaterialCategory>
 */
class LearningMaterialCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Category '.fake()->unique()->word(),
            'slug' => 'category-'.fake()->unique()->slug(),
            'description' => fake()->sentence(),
        ];
    }
}
