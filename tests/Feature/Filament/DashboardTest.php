<?php

use App\Filament\Pages\Dashboard;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => actingAs(User::factory()->create()));

it('exposes a sync to algolia action on the dashboard', function () {
    Livewire::test(Dashboard::class)
        ->assertActionExists('syncAlgolia');
});

it('imports products into the search index when synced', function () {
    Product::factory()->count(2)->create();

    Livewire::test(Dashboard::class)
        ->callAction('syncAlgolia')
        ->assertNotified()
        ->assertHasNoActionErrors();
});
