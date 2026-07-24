<?php

namespace Database\Factories;

use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Webhook>
 */
class WebhookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $itemGroup = fake()->randomElement(['products', 'variants']);
        $itemAction = fake()->randomElement(['created', 'updated', 'deleted']);
        $language = 'nl';

        return [
            'lightspeed_id' => fake()->unique()->numberBetween(1, 100000),
            'item_group' => $itemGroup,
            'item_action' => $itemAction,
            'language' => $language,
            'address' => "https://example.test/webhooks/{$language}/{$itemGroup}/{$itemAction}",
            'format' => 'json',
            'is_active' => true,
        ];
    }
}
