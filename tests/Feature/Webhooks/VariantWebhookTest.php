<?php

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function variantPayload(int $id, int $productId, array $overrides = []): array
{
    return [
        'variant' => [
            'id' => $id,
            'createdAt' => '2019-07-24T15:23:06+00:00',
            'updatedAt' => '2019-08-01T16:10:58+00:00',
            'isDefault' => true,
            'sortOrder' => 1,
            'articleCode' => 'ART-1',
            'ean' => '1234567890123',
            'sku' => 'SKU-1',
            'priceExcl' => 20.00,
            'priceIncl' => 24.20,
            'stockTracking' => 'enabled',
            'stockLevel' => 100,
            'weight' => 200,
            'title' => 'Medium',
            'image' => null,
            'product' => ['resource' => ['id' => $productId]],
            ...$overrides,
        ],
    ];
}

it('can store a variant via created webhook', function () {
    $product = Product::factory()->create();
    $id = rand(1000, 9999);

    $this->post(route('webhooks.variants.created', ['language' => 'nl']), variantPayload($id, $product->id))
        ->assertSuccessful();

    $variant = Variant::find($id);

    expect($variant)->not->toBeNull()
        ->and($variant->title)->toBe('Medium')
        ->and($variant->product_id)->toBe($product->id);
});

it('can update a variant via updated webhook', function () {
    $product = Product::factory()->create();
    $id = rand(1000, 9999);

    $this->post(route('webhooks.variants.created', ['language' => 'nl']), variantPayload($id, $product->id))
        ->assertSuccessful();

    $this->post(
        route('webhooks.variants.updated', ['language' => 'nl']),
        variantPayload($id, $product->id, ['title' => 'Large', 'stockLevel' => 42])
    )->assertSuccessful();

    $variant = Variant::find($id);

    expect($variant->title)->toBe('Large')
        ->and($variant->stock_level)->toBe(42);
});

it('can soft delete a variant via deleted webhook', function () {
    $product = Product::factory()->create();
    $id = rand(1000, 9999);

    $this->post(route('webhooks.variants.created', ['language' => 'nl']), variantPayload($id, $product->id))
        ->assertSuccessful();

    $this->post(route('webhooks.variants.deleted', ['language' => 'nl']), ['resource_id' => $id])
        ->assertSuccessful();

    expect(Variant::find($id))->toBeNull()
        ->and(Variant::withTrashed()->find($id))->not->toBeNull();
});

it('returns success even when deleting a non-existent variant', function () {
    $this->post(route('webhooks.variants.deleted', ['language' => 'nl']), ['resource_id' => 99999])
        ->assertSuccessful();
});
