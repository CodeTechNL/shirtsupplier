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

it('keys products by position and exposes only id, url and image', function () {
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
        ->assertJsonPath('data.products.1.id', $sameProduct->id)
        ->assertJsonPath('data.products.1.url', 'my-product-slug')
        ->assertJsonPath('data.products.1.image.src', 'https://cdn.example.com/image.jpg')
        ->assertJsonPath('data.products.1.image.createdAt', '2026-03-20T11:12:20+01:00')
        ->assertExactJson([
            'data' => [
                'products' => [
                    '1' => [
                        'id' => $sameProduct->id,
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

it('returns products in the order set in the admin', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    [$first, $second, $third] = Product::factory()->count(3)->create()->all();

    $group->products()->attach([
        $product->id => ['sort_order' => 1],
        $third->id => ['sort_order' => 2],
        $first->id => ['sort_order' => 3],
        $second->id => ['sort_order' => 4],
    ]);

    $response = $this->getJson('/api/same-products?product='.$product->id);

    $response->assertOk()
        ->assertJsonPath('data.products.1.id', $third->id)
        ->assertJsonPath('data.products.2.id', $first->id)
        ->assertJsonPath('data.products.3.id', $second->id);

    expect(array_keys($response->json('data.products')))->toBe([1, 2, 3]);
});

it('serialises products as a keyed object rather than a json array', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    $sameProducts = Product::factory()->count(2)->create();

    $group->attachProductsToEnd([$product->id, ...$sameProducts->pluck('id')]);

    $content = $this->getJson('/api/same-products?product='.$product->id)->getContent();

    /** A JSON array would drop the position keys and start numbering at 0. */
    expect($content)->toContain('"products":{"1":{')
        ->and($content)->not->toContain('"products":[');
});

it('keys products so that ascending key order is the admin order', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    [$first, $second, $third] = Product::factory()->count(3)->create()->all();

    $group->products()->attach([
        $product->id => ['sort_order' => 1],
        $third->id => ['sort_order' => 2],
        $first->id => ['sort_order' => 3],
        $second->id => ['sort_order' => 4],
    ]);

    $products = $this->getJson('/api/same-products?product='.$product->id)->json('data.products');

    /** Sorting the keys ascending is what a JavaScript client does implicitly. */
    ksort($products);

    expect(array_column($products, 'id'))->toBe([$third->id, $first->id, $second->id]);
});

it('numbers the position keys consecutively when hidden ones are skipped', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    $visible = Product::factory()->create();
    $hidden = Product::factory()->invisible()->create();

    $group->products()->attach([
        $product->id => ['sort_order' => 1],
        $hidden->id => ['sort_order' => 2],
        $visible->id => ['sort_order' => 3],
    ]);

    $this->getJson('/api/same-products?product='.$product->id)
        ->assertOk()
        ->assertJsonCount(1, 'data.products')
        ->assertJsonPath('data.products.1.id', $visible->id);
});

it('returns 422 when product parameter is missing', function () {
    $this->getJson('/api/same-products')
        ->assertUnprocessable();
});

it('returns 422 when product does not exist', function () {
    $this->getJson('/api/same-products?product=999')
        ->assertUnprocessable();
});
