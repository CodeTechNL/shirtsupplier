<?php

use App\Jobs\SyncVariants;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

it('dispatches the sync variants job via artisan command', function () {
    Queue::fake();

    $this->artisan('variants:sync')
        ->expectsOutput('Dispatching variant sync job...')
        ->expectsOutput('Job dispatched. Variants will be synced shortly.')
        ->assertSuccessful();

    Queue::assertPushed(SyncVariants::class);
});

it('downloads variants from the api', function () {
    $apiVariants = [
        [
            'id' => 202,
            'title' => 'Medium',
            'sku' => 'SKU-1',
            'priceIncl' => 24.20,
            'product' => ['resource' => ['id' => 101]],
        ],
    ];

    $variantsResource = Mockery::mock();
    $variantsResource->shouldReceive('count')->andReturn(1);
    $variantsResource->shouldReceive('get')->with(null, Mockery::on(fn ($params) => $params['page'] === 1 && $params['limit'] === 250))->andReturn($apiVariants);

    $apiClient = Mockery::mock(WebshopappApiClient::class);
    $apiClient->variants = $variantsResource;

    $job = new SyncVariants;

    $reflection = new ReflectionMethod($job, 'downloadVariants');
    $data = $reflection->invoke($job, $apiClient);

    expect($data)->toHaveCount(1)
        ->and($data[0]['title'])->toBe('Medium');
});

it('persists variants to the database', function () {
    $product = Product::factory()->create(['id' => 101]);

    $data = [
        202 => [
            'id' => 202,
            'title' => 'Medium',
            'sku' => 'SKU-1',
            'ean' => '1234567890123',
            'articleCode' => 'ART-1',
            'isDefault' => true,
            'sortOrder' => 2,
            'priceExcl' => 20.00,
            'priceIncl' => 24.20,
            'stockTracking' => 'enabled',
            'stockLevel' => 15,
            'weight' => 200,
            'product' => ['resource' => ['id' => $product->id]],
        ],
    ];

    $job = new SyncVariants;

    $reflection = new ReflectionMethod($job, 'persistVariants');
    $reflection->invoke($job, $data);

    $variant = Variant::find(202);

    expect($variant)->not->toBeNull()
        ->and($variant->title)->toBe('Medium')
        ->and($variant->sku)->toBe('SKU-1')
        ->and($variant->article_code)->toBe('ART-1')
        ->and($variant->is_default)->toBeTrue()
        ->and($variant->product_id)->toBe($product->id)
        ->and((float) $variant->price_incl)->toBe(24.20);
});

it('soft-deletes variants no longer in the api', function () {
    $existing = Variant::factory()->create();

    $data = [
        999 => ['id' => 999],
    ];

    $job = new SyncVariants;

    $reflection = new ReflectionMethod($job, 'deleteRemovedVariants');
    $reflection->invoke($job, $data);

    expect(Variant::find($existing->id))->toBeNull()
        ->and(Variant::withTrashed()->find($existing->id))->not->toBeNull();
});
