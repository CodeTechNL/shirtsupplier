<?php

use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Resources\Products\ProductResource;
use App\Jobs\SyncProducts;
use App\Jobs\Webhooks\InstallProductWebhooks;
use App\Models\Product;
use App\Models\SameProductGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can list products', function () {
    $products = Product::factory()->count(3)->create();

    Livewire\Livewire::test(ListProducts::class)
        ->assertOk()
        ->assertCanSeeTableRecords($products);
});

it('can view a product', function () {
    $product = Product::factory()->create();

    Livewire\Livewire::test(ViewProduct::class, ['record' => $product->id])
        ->assertOk();
});

it('shows same product groups on the view page', function () {
    $product = Product::factory()->create();
    $group = SameProductGroup::factory()->create();
    $product->sameProductGroups()->attach($group);

    Livewire\Livewire::test(ViewProduct::class, ['record' => $product->id])
        ->assertOk()
        ->assertSee($group->name);
});

it('cannot create products', function () {
    expect(ProductResource::canCreate())->toBeFalse();
});

it('can search products by title', function () {
    $matching = Product::factory()->create([
        'fulltitle' => ['nl' => 'Matching Product'],
    ]);
    $other = Product::factory()->create([
        'fulltitle' => ['nl' => 'Other Item'],
    ]);

    Livewire\Livewire::test(ListProducts::class)
        ->searchTable('Matching')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can dispatch the install webhooks job via the action', function () {
    Queue::fake();

    Livewire\Livewire::test(ListProducts::class)
        ->callAction('installWebhooks')
        ->assertNotified();

    Queue::assertPushed(InstallProductWebhooks::class);
});

it('can dispatch the sync products job via the action', function () {
    Queue::fake();

    Livewire\Livewire::test(ListProducts::class)
        ->callAction('syncProducts')
        ->assertNotified();

    Queue::assertPushed(SyncProducts::class);
});
