<?php

use App\Filament\Resources\SameProductGroups\Pages\EditSameProductGroup;
use App\Filament\Resources\SameProductGroups\Pages\ListSameProductGroups;
use App\Filament\Resources\SameProductGroups\RelationManagers\ProductsRelationManager;
use App\Models\Product;
use App\Models\SameProductGroup;
use App\Models\User;
use App\Models\Variant;
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

it('can delete a group from the list table row action', function () {
    $group = SameProductGroup::factory()->create();

    Livewire\Livewire::test(ListSameProductGroups::class)
        ->callAction(TestAction::make(DeleteAction::class)->table($group))
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

it('excludes only products already in the given group, allowing multiple groups', function () {
    $group = SameProductGroup::factory()->create();
    $otherGroup = SameProductGroup::factory()->create();

    $free = Product::factory()->create();
    $inThisGroup = Product::factory()->create();
    $inOtherGroup = Product::factory()->create();

    $group->products()->attach($inThisGroup);
    $otherGroup->products()->attach($inOtherGroup);

    $ids = ProductsRelationManager::attachableProductsQuery(excludeGroup: $group)->pluck('id');

    expect($ids)->toContain($free->id)
        ->toContain($inOtherGroup->id)
        ->not->toContain($inThisGroup->id);
});

it('finds attachable products by their variant sku, ean or article code', function () {
    $bySku = Product::factory()->create();
    Variant::factory()->for($bySku)->create(['sku' => 'SKU-ABC', 'ean' => '', 'article_code' => '']);

    $byEan = Product::factory()->create();
    Variant::factory()->for($byEan)->create(['sku' => '', 'ean' => '5099206084247', 'article_code' => '']);

    $byArticleCode = Product::factory()->create();
    Variant::factory()->for($byArticleCode)->create(['sku' => '', 'ean' => '', 'article_code' => 'ART-XYZ']);

    expect(array_keys(ProductsRelationManager::searchAttachableProducts('SKU-ABC')))->toBe([$bySku->id])
        ->and(array_keys(ProductsRelationManager::searchAttachableProducts('5099206084247')))->toBe([$byEan->id])
        ->and(array_keys(ProductsRelationManager::searchAttachableProducts('ART-XYZ')))->toBe([$byArticleCode->id]);
});

it('finds attachable products by product title and variant title', function () {
    $byProductTitle = Product::factory()->create(['fulltitle' => ['nl' => 'Uniquely Named Shirt']]);

    $byVariantTitle = Product::factory()->create(['fulltitle' => ['nl' => 'Plain Shirt']]);
    Variant::factory()->for($byVariantTitle)->create(['title' => 'XXL-Special']);

    expect(array_keys(ProductsRelationManager::searchAttachableProducts('Uniquely Named')))->toBe([$byProductTitle->id])
        ->and(array_keys(ProductsRelationManager::searchAttachableProducts('XXL-Special')))->toBe([$byVariantTitle->id]);
});
