<?php

use App\Jobs\SyncVariants;
use App\Models\Variant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('syncs variants from the api', function () {
    $apiVariants = [
        [
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
            'image' => ['src' => 'https://example.com/variant.jpg'],
            'product' => ['resource' => ['id' => 101]],
        ],
    ];

    $variantsResource = Mockery::mock();
    $variantsResource->shouldReceive('count')->andReturn(1);
    $variantsResource->shouldReceive('get')->with(null, Mockery::on(fn ($params) => $params['page'] === 1 && $params['limit'] === 250))->andReturn($apiVariants);

    $apiClient = Mockery::mock(WebshopappApiClient::class);
    $apiClient->variants = $variantsResource;

    $job = new SyncVariants;

    $reflection = new ReflectionMethod($job, 'syncVariants');
    $reflection->invoke($job, $apiClient, now());

    $variant = Variant::find(202);

    expect($variant)->not->toBeNull()
        ->and($variant->title)->toBe('Medium')
        ->and($variant->sku)->toBe('SKU-1')
        ->and($variant->article_code)->toBe('ART-1')
        ->and($variant->is_default)->toBeTrue()
        ->and($variant->product_id)->toBe(101)
        ->and($variant->image)->toBe(['src' => 'https://example.com/variant.jpg'])
        ->and((float) $variant->price_incl)->toBe(24.20);
});

it('updates an existing variant instead of duplicating it', function () {
    $existing = Variant::factory()->create(['id' => 202, 'title' => 'Old Title', 'stock_level' => 1]);

    $apiVariants = [
        ['id' => 202, 'title' => 'New Title', 'stockLevel' => 99, 'product' => ['resource' => ['id' => 101]]],
    ];

    $variantsResource = Mockery::mock();
    $variantsResource->shouldReceive('count')->andReturn(1);
    $variantsResource->shouldReceive('get')->andReturn($apiVariants);

    $apiClient = Mockery::mock(WebshopappApiClient::class);
    $apiClient->variants = $variantsResource;

    $job = new SyncVariants;

    (new ReflectionMethod($job, 'syncVariants'))->invoke($job, $apiClient, now());

    expect(Variant::count())->toBe(1)
        ->and(Variant::find(202)->title)->toBe('New Title')
        ->and(Variant::find(202)->stock_level)->toBe(99);
});

it('soft-deletes variants not touched by the sync', function () {
    $stale = Variant::factory()->create();
    $fresh = Variant::factory()->create();

    $syncedAt = now();
    DB::table('variants')->where('id', $stale->id)->update(['updated_at' => $syncedAt->copy()->subDay()]);
    DB::table('variants')->where('id', $fresh->id)->update(['updated_at' => $syncedAt->copy()->addMinute()]);

    $job = new SyncVariants;

    $reflection = new ReflectionMethod($job, 'deleteRemovedVariants');
    $reflection->invoke($job, $syncedAt);

    expect(Variant::find($stale->id))->toBeNull()
        ->and(Variant::withTrashed()->find($stale->id))->not->toBeNull()
        ->and(Variant::find($fresh->id))->not->toBeNull();
});

it('restores a soft-deleted variant that reappears in the api', function () {
    $variant = Variant::factory()->create(['id' => 202]);
    $variant->delete();

    expect(Variant::find(202))->toBeNull();

    $apiVariants = [
        ['id' => 202, 'title' => 'Back Again', 'product' => ['resource' => ['id' => 101]]],
    ];

    $variantsResource = Mockery::mock();
    $variantsResource->shouldReceive('count')->andReturn(1);
    $variantsResource->shouldReceive('get')->andReturn($apiVariants);

    $apiClient = Mockery::mock(WebshopappApiClient::class);
    $apiClient->variants = $variantsResource;

    $job = new SyncVariants;

    (new ReflectionMethod($job, 'syncVariants'))->invoke($job, $apiClient, now());

    expect(Variant::find(202))->not->toBeNull()
        ->and(Variant::find(202)->title)->toBe('Back Again');
});
