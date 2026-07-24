<?php

use App\Filament\Resources\Variants\Pages\ListVariants;
use App\Filament\Resources\Variants\Pages\ViewVariant;
use App\Filament\Resources\Variants\VariantResource;
use App\Jobs\SyncVariants;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can list variants', function () {
    $variants = Variant::factory()->count(3)->create();

    Livewire\Livewire::test(ListVariants::class)
        ->assertOk()
        ->assertCanSeeTableRecords($variants);
});

it('can view a variant', function () {
    $variant = Variant::factory()->create();

    Livewire\Livewire::test(ViewVariant::class, ['record' => $variant->id])
        ->assertOk();
});

it('cannot create variants', function () {
    expect(VariantResource::canCreate())->toBeFalse();
});

it('can search variants by sku', function () {
    $matching = Variant::factory()->create(['sku' => 'MATCH-123']);
    $other = Variant::factory()->create(['sku' => 'OTHER-999']);

    Livewire\Livewire::test(ListVariants::class)
        ->searchTable('MATCH-123')
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$other]);
});

it('can dispatch the sync variants job via the action', function () {
    Queue::fake();

    Livewire\Livewire::test(ListVariants::class)
        ->callAction('syncVariants')
        ->assertNotified();

    Queue::assertPushed(SyncVariants::class);
});
