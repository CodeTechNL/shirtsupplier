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
        ->assertJsonCount(3, 'data.products')
        ->assertJsonPath('data.count', 3)
        ->assertJsonPath('meta', [])
        ->assertJsonPath('status.code', 200)
        ->assertJsonPath('status.status', 'success');
});

it('keys products by id and exposes only url and image', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    $sameProduct = Product::factory()->create([
        'url' => ['nl' => 'my-product-slug'],
        'image' => [
            'src' => 'https://cdn.example.com/image.jpg',
            'size' => 1234,
            'thumb' => 'https://cdn.example.com/50x50/image.jpg',
            'title' => 'My image',
            'createdAt' => '2026-03-20T11:12:20+01:00',
            'extension' => 'jpg',
            'updatedAt' => '2026-03-20T11:12:20+01:00',
        ],
    ]);

    $group->products()->attach([$product->id, $sameProduct->id]);

    $response = $this->getJson('/api/same-products?product='.$product->id);

    $response->assertOk()
        ->assertJsonPath("data.products.{$sameProduct->id}.url", 'my-product-slug')
        ->assertJsonPath("data.products.{$sameProduct->id}.image.src", 'https://cdn.example.com/image.jpg')
        ->assertJsonPath("data.products.{$sameProduct->id}.image.createdAt", '2026-03-20T11:12:20+01:00')
        ->assertExactJson([
            'data' => [
                'products' => [
                    (string) $sameProduct->id => [
                        'url' => 'my-product-slug',
                        'image' => [
                            'createdAt' => '2026-03-20T11:12:20+01:00',
                            'updatedAt' => '2026-03-20T11:12:20+01:00',
                            'extension' => 'jpg',
                            'size' => 1234,
                            'title' => 'My image',
                            'thumb' => 'https://cdn.example.com/50x50/image.jpg',
                            'src' => 'https://cdn.example.com/image.jpg',
                        ],
                    ],
                ],
                'count' => 1,
            ],
            'meta' => [],
            'status' => [
                'code' => 200,
                'status' => 'success',
            ],
        ]);
});

it('returns empty products object when product has no group', function () {
    $product = Product::factory()->create();

    $response = $this->getJson('/api/same-products?product='.$product->id);

    $response->assertOk()
        ->assertJsonPath('data.products', [])
        ->assertJsonPath('data.count', 0)
        ->assertJsonPath('status.code', 200);
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
        ->assertJsonCount(1, 'data.products')
        ->assertJsonPath('data.count', 1);
});

it('returns 422 when product parameter is missing', function () {
    $this->getJson('/api/same-products')
        ->assertUnprocessable();
});

it('returns 422 when product does not exist', function () {
    $this->getJson('/api/same-products?product=999')
        ->assertUnprocessable();
});
