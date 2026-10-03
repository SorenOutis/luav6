<?php

namespace Database\Factories;

use App\Models\Merch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merch>
 */
class MerchFactory extends Factory
{
    protected $model = Merch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'price' => fake()->randomFloat(2, 299, 1999),
            'currency' => 'PHP',
            'stock' => fake()->numberBetween(0, 50),
            'is_out_of_stock' => false,
            'image_path' => null,
            'button_url' => 'https://koamishin.com/',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => 0,
            'is_out_of_stock' => true,
        ]);
    }

    public function inStock(int $stock = 25): static
    {
        return $this->state(fn (array $attributes) => [
            'stock' => $stock,
            'is_out_of_stock' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
