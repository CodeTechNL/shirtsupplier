<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

function productPayload(int $id, array $overrides = []): array
{
    return [
        'product' => [
            'id' => $id,
            'createdAt' => '2019-05-28T15:25:46+00:00',
            'updatedAt' => '2019-05-28T17:16:16+00:00',
            'isVisible' => true,
            'url' => 'lookin-sharp-tee',
            'title' => 'Lookin Sharp T-Shirt',
            'fulltitle' => 'Lookin Sharp T-Shirt',
            'description' => 'Description of the Lookin Sharp T-Shirt',
            'content' => '<p>Long Description</p>',
            'image' => null,
            ...$overrides,
        ],
    ];
}

it('can store a product via created webhook', function () {
    $id = rand(1000, 9999);

    $this->post(route('webhooks.products.created', ['language' => 'nl']), productPayload($id))
        ->assertSuccessful();

    $product = Product::find($id);

    expect($product)->not->toBeNull()
        ->and($product->getTranslation('nl', 'title'))->toBe('Lookin Sharp T-Shirt');
});

it('can update a product via updated webhook', function () {
    $id = rand(1000, 9999);

    $this->post(route('webhooks.products.created', ['language' => 'nl']), productPayload($id))
        ->assertSuccessful();

    $this->post(
        route('webhooks.products.updated', ['language' => 'nl']),
        productPayload($id, ['title' => 'Updated Title'])
    )->assertSuccessful();

    $product = Product::find($id);

    expect($product->getTranslation('nl', 'title'))->toBe('Updated Title');
});

it('preserves translations when updating in a different language', function () {
    $id = rand(1000, 9999);

    $this->post(route('webhooks.products.created', ['language' => 'nl']), productPayload($id))
        ->assertSuccessful();

    $this->post(
        route('webhooks.products.updated', ['language' => 'en']),
        productPayload($id, ['title' => 'English Title'])
    )->assertSuccessful();

    $product = Product::find($id);

    expect($product->getTranslation('nl', 'title'))->toBe('Lookin Sharp T-Shirt')
        ->and($product->getTranslation('en', 'title'))->toBe('English Title');
});

it('can soft delete a product via deleted webhook', function () {
    $id = rand(1000, 9999);

    $this->post(route('webhooks.products.created', ['language' => 'nl']), productPayload($id))
        ->assertSuccessful();

    $this->post(route('webhooks.products.deleted', ['language' => 'nl']), ['resource_id' => $id])
        ->assertSuccessful();

    expect(Product::find($id))->toBeNull()
        ->and(Product::withTrashed()->find($id))->not->toBeNull();
});

it('ignores a webhook without a product payload', function (string $route, array $payload) {
    Queue::fake();

    $this->post(route($route, ['language' => 'nl']), $payload)
        ->assertSuccessful();

    Queue::assertNothingPushed();
})->with([
    'created without payload' => ['webhooks.products.created', []],
    'created without id' => ['webhooks.products.created', ['product' => ['title' => 'No Id']]],
    'updated without payload' => ['webhooks.products.updated', []],
    'updated without id' => ['webhooks.products.updated', ['product' => ['title' => 'No Id']]],
]);

it('returns success even when deleting a non-existent product', function () {
    $this->post(route('webhooks.products.deleted', ['language' => 'nl']), ['resource_id' => 99999])
        ->assertSuccessful();
});
