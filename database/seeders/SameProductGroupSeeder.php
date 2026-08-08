<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\SameProductGroup;
use Illuminate\Database\Seeder;

class SameProductGroupSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::all();

        if ($products->isEmpty()) {
            $this->command->warn('No products found. Run ProductSeeder first.');

            return;
        }

        $availableProducts = $products->shuffle();

        SameProductGroup::factory()
            ->count(5)
            ->create()
            ->each(function (SameProductGroup $group) use (&$availableProducts) {
                $take = min(rand(2, 5), $availableProducts->count());
                $group->attachProductsToEnd($availableProducts->splice(0, $take)->pluck('id')->all());
            });
    }
}
