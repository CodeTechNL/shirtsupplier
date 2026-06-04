<?php

use App\Filament\Resources\SameProductGroups\Pages\EditSameProductGroup;
use App\Filament\Resources\SameProductGroups\Pages\ListSameProductGroups;
use App\Filament\Resources\SameProductGroups\RelationManagers\ProductsRelationManager;
use App\Models\Product;
use App\Models\SameProductGroup;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can list groups', function () {
    $groups = SameProductGroup::factory()->count(3)->create();

    Livewire\Livewire::test(ListSameProductGroups::class)
        ->assertOk()
        ->assertCanSeeTableRecords($groups);
});

it('can create a group via modal', function () {
    Livewire\Livewire::test(ListSameProductGroups::class)
        ->callAction(CreateAction::class, ['name' => 'Test Group'])
        ->assertNotified();

    $this->assertDatabaseHas(SameProductGroup::class, ['name' => 'Test Group']);
});

it('can edit a group name', function () {
    $group = SameProductGroup::factory()->create();

    Livewire\Livewire::test(EditSameProductGroup::class, ['record' => $group->id])
        ->fillForm(['name' => 'Updated Name'])
        ->call('save')
        ->assertNotified();

    expect($group->fresh()->name)->toBe('Updated Name');
});

it('can delete a group', function () {
    $group = SameProductGroup::factory()->create();

    Livewire\Livewire::test(EditSameProductGroup::class, ['record' => $group->id])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    $this->assertDatabaseMissing(SameProductGroup::class, ['id' => $group->id]);
});

it('can load the products relation manager', function () {
    $group = SameProductGroup::factory()->create();
    $products = Product::factory()->count(3)->create();
    $group->products()->attach($products->pluck('id'));

    Livewire\Livewire::test(ProductsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditSameProductGroup::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($products);
});

it('validates that name is required on create', function () {
    Livewire\Livewire::test(ListSameProductGroups::class)
        ->callAction(CreateAction::class, ['name' => null])
        ->assertHasActionErrors(['name' => 'required'])
        ->assertNotNotified();
});
