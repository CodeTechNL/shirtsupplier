<?php

use App\Models\Product;
use App\Models\SameProductGroup;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('returns same products for a product in a group', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    $sameProducts = Product::factory()->count(3)->create();

    $group->products()->attach([$product->id, ...$sameProducts->pluck('id')]);

    $response = $this->getJson('/api/same-products?product='.$product->id);

    $response->assertOk()
        ->assertJsonCount(3, 'products')
        ->assertJsonPath('count', 3);
});

it('returns empty when product has no group', function () {
    $product = Product::factory()->create();

    $response = $this->getJson('/api/same-products?product='.$product->id);

    $response->assertOk()
        ->assertJsonPath('products', [])
        ->assertJsonPath('count', 0);
});

it('returns only visible products from the group', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    $visibleProduct = Product::factory()->create();
    $invisibleProduct = Product::factory()->invisible()->create();

    $group->products()->attach([
        $product->id,
        $visibleProduct->id,
        $invisibleProduct->id,
    ]);

    $response = $this->getJson('/api/same-products?product='.$product->id);

    $response->assertOk()
        ->assertJsonCount(1, 'products')
        ->assertJsonPath('count', 1);
});

it('returns 422 when product parameter is missing', function () {
    $this->getJson('/api/same-products')
        ->assertUnprocessable();
});

it('returns 422 when product does not exist', function () {
    $this->getJson('/api/same-products?product=999')
        ->assertUnprocessable();
});
