<?php

use App\Filament\Widgets\ProductSyncOverview;
use App\Filament\Widgets\VariantSyncOverview;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function fakeLightspeedResource(string $property, $count): void
{
    $resource = Mockery::mock();

    if ($count instanceof Throwable) {
        $resource->shouldReceive('count')->andThrow($count);
    } else {
        $resource->shouldReceive('count')->andReturn($count);
    }

    $client = Mockery::mock(WebshopappApiClient::class);
    $client->{$property} = $resource;

    app()->instance(WebshopappApiClient::class, $client);
}

it('marks products as in sync when the counts match', function () {
    Product::factory()->count(2)->create();
    fakeLightspeedResource('products', 2);

    Livewire::test(ProductSyncOverview::class)
        ->assertOk()
        ->assertSee('In sync')
        ->assertDontSee('Out of sync');
});

it('marks products as out of sync when the counts differ', function () {
    Product::factory()->count(2)->create();
    fakeLightspeedResource('products', 5);

    Livewire::test(ProductSyncOverview::class)
        ->assertOk()
        ->assertSee('Out of sync')
        ->assertSee('2 in database');
});

it('marks variants as in sync when the counts match', function () {
    $product = Product::factory()->create();
    Variant::factory()->count(3)->create(['product_id' => $product->id]);
    fakeLightspeedResource('variants', 3);

    Livewire::test(VariantSyncOverview::class)
        ->assertOk()
        ->assertSee('In sync');
});

it('shows a failure state when Lightspeed cannot be reached', function () {
    Product::factory()->create();
    fakeLightspeedResource('products', new RuntimeException('API down'));

    Livewire::test(ProductSyncOverview::class)
        ->assertOk()
        ->assertSee('Could not reach Lightspeed');
});

it('caches the live Lightspeed count for fifteen minutes', function () {
    Product::factory()->create();

    $resource = Mockery::mock();
    $resource->shouldReceive('count')->once()->andReturn(1);

    $client = Mockery::mock(WebshopappApiClient::class);
    $client->products = $resource;
    app()->instance(WebshopappApiClient::class, $client);

    Livewire::test(ProductSyncOverview::class)->assertOk();
    Livewire::test(ProductSyncOverview::class)->assertOk();

    expect(Cache::get('lightspeed.count.products'))->toBe(1);
});
