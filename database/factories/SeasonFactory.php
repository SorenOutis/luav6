<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Season>
 */
class SeasonFactory extends Factory
{
    protected $model = Season::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Season '.fake()->unique()->numberBetween(1, 9999),
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'is_active' => false,
        ];
    }

    /**
     * The currently running season.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'start_date' => now()->subWeek(),
            'end_date' => now()->addWeek(),
        ]);
    }
}
