<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'is_visible' => true,
            'title' => ['nl' => fake()->sentence(3)],
            'url' => ['nl' => fake()->slug()],
            'fulltitle' => ['nl' => fake()->sentence(4)],
            'description' => ['nl' => fake()->paragraph()],
            'content' => ['nl' => fake()->paragraphs(2, true)],
            'image' => null,
        ];
    }

    public function invisible(): static
    {
        return $this->state(['is_visible' => false]);
    }
}
