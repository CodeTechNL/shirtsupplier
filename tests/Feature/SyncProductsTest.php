<?php

use App\Jobs\SyncProducts;
use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

it('dispatches the sync products job via artisan command', function () {
    Queue::fake();

    $this->artisan('products:sync')
        ->expectsOutput('Dispatching product sync job...')
        ->expectsOutput('Job dispatched. Products will be synced shortly.')
        ->assertSuccessful();

    Queue::assertPushed(SyncProducts::class);
});

it('creates new products from the api response', function () {
    $apiProducts = [
        [
            'id' => 101,
            'title' => 'Test Product',
            'url' => 'test-product',
            'fulltitle' => 'Test Full Product',
            'content' => 'Product content',
            'description' => 'Product description',
            'isVisible' => true,
            'image' => ['src' => 'https://example.com/image.jpg'],
        ],
    ];

    $productsResource = Mockery::mock();
    $productsResource->shouldReceive('count')->andReturn(1);
    $productsResource->shouldReceive('get')->with(null, Mockery::on(fn ($params) => $params['page'] === 1 && $params['limit'] === 250 && ! isset($params['fields'])))->andReturn($apiProducts);

    $apiClient = Mockery::mock(\WebshopappApiClient::class);
    $apiClient->products = $productsResource;

    config()->set('webshop.languages', ['nl']);

    $job = new SyncProducts;

    $reflection = new ReflectionMethod($job, 'downloadProducts');
    $data = $reflection->invoke($job, $apiClient, 'nl');

    expect($data)->toHaveCount(1)
        ->and($data[0]['title'])->toBe(['nl' => 'Test Product']);
});

it('persists products to the database', function () {
    $data = [
        101 => [
            'id' => 101,
            'title' => ['nl' => 'Test Product'],
            'url' => ['nl' => 'test-product'],
            'fulltitle' => ['nl' => 'Test Full Product'],
            'content' => ['nl' => 'Content here'],
            'description' => 'Description',
            'isVisible' => true,
            'image' => ['src' => 'https://example.com/image.jpg'],
        ],
    ];

    $job = new SyncProducts;

    $reflection = new ReflectionMethod($job, 'persistProducts');
    $reflection->invoke($job, $data);

    expect(Product::find(101))
        ->not->toBeNull()
        ->and(Product::find(101)->getTranslation('nl', 'title'))->toBe('Test Product');
});

it('soft-deletes products no longer in the api', function () {
    $existing = Product::factory()->create();

    $data = [
        999 => ['id' => 999],
    ];

    $job = new SyncProducts;

    $reflection = new ReflectionMethod($job, 'deleteRemovedProducts');
    $reflection->invoke($job, $data);

    expect(Product::find($existing->id))->toBeNull()
        ->and(Product::withTrashed()->find($existing->id))->not->toBeNull();
});
