<?php

namespace Database\Factories;

use App\Models\LearningMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearningMaterial>
 */
class LearningMaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Material '.fake()->words(3, true),
            'description' => fake()->sentence(),
            'file_path' => 'library/test.pdf',
            'file_name' => 'test.pdf',
            'file_size' => 12345,
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'status' => 'published',
            'is_downloadable' => true,
            'sort_order' => 0,
        ];
    }
}
