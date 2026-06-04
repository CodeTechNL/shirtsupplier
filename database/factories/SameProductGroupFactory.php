<?php

namespace Database\Factories;

use App\Models\SameProductGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SameProductGroup>
 */
class SameProductGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
        ];
    }
}
