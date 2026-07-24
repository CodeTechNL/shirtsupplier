<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Variant>
 */
class VariantFactory extends Factory
{
    public function definition(): array
    {
        $priceExcl = fake()->randomFloat(2, 5, 100);

        return [
            'product_id' => Product::factory(),
            'title' => fake()->randomElement(['Small', 'Medium', 'Large', 'X-Large']),
            'sku' => fake()->unique()->bothify('SKU-####'),
            'ean' => fake()->ean13(),
            'article_code' => fake()->bothify('ART-####'),
            'is_default' => false,
            'sort_order' => fake()->numberBetween(1, 10),
            'price_excl' => $priceExcl,
            'price_incl' => round($priceExcl * 1.21, 2),
            'old_price_excl' => 0,
            'old_price_incl' => 0,
            'stock_tracking' => 'enabled',
            'stock_level' => fake()->numberBetween(0, 100),
            'weight' => fake()->numberBetween(0, 500),
            'image' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(['is_default' => true]);
    }
}
