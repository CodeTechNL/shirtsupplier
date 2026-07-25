<?php

use App\Filament\Pages\Dashboard;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => actingAs(User::factory()->create()));

it('exposes a sync search engine action on the dashboard', function () {
    Livewire::test(Dashboard::class)
        ->assertActionExists('syncAlgolia', fn ($action): bool => $action->getLabel() === 'Sync search engine');
});

it('imports products into the search index when synced', function () {
    Product::factory()->count(2)->create();

    Livewire::test(Dashboard::class)
        ->callAction('syncAlgolia')
        ->assertNotified()
        ->assertHasNoActionErrors();
});

it('hides the clear metrics action from regular users', function () {
    Livewire::test(Dashboard::class)
        ->assertActionHidden('clearMetrics');
});

it('shows the clear metrics action to super admins', function () {
    actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Dashboard::class)
        ->assertActionVisible('clearMetrics');
});

it('clears the cached product and variant metrics', function () {
    actingAs(User::factory()->superAdmin()->create());

    Cache::put('lightspeed.count.products', 100, 900);
    Cache::put('lightspeed.count.variants', 200, 900);

    Livewire::test(Dashboard::class)
        ->callAction('clearMetrics')
        ->assertNotified('Product and variant metrics cleared.');

    expect(Cache::missing('lightspeed.count.products'))->toBeTrue()
        ->and(Cache::missing('lightspeed.count.variants'))->toBeTrue();
});
