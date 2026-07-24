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

it('only lists attachable products that are not yet in a group', function () {
    $free = Product::factory()->create();
    $grouped = Product::factory()->create();
    SameProductGroup::factory()->create()->products()->attach($grouped);

    $ids = ProductsRelationManager::attachableProductsQuery()->pluck('id');

    expect($ids)->toContain($free->id)
        ->not->toContain($grouped->id);
});

it('finds attachable products by their variant sku, ean or article code', function () {
    $bySku = Product::factory()->create();
    Variant::factory()->for($bySku)->create(['sku' => 'SKU-ABC', 'ean' => '', 'article_code' => '']);

    $byEan = Product::factory()->create();
    Variant::factory()->for($byEan)->create(['sku' => '', 'ean' => '5099206084247', 'article_code' => '']);

    $byArticleCode = Product::factory()->create();
    Variant::factory()->for($byArticleCode)->create(['sku' => '', 'ean' => '', 'article_code' => 'ART-XYZ']);

    expect(ProductsRelationManager::attachableProductsQuery('SKU-ABC')->pluck('id')->all())->toBe([$bySku->id])
        ->and(ProductsRelationManager::attachableProductsQuery('5099206084247')->pluck('id')->all())->toBe([$byEan->id])
        ->and(ProductsRelationManager::attachableProductsQuery('ART-XYZ')->pluck('id')->all())->toBe([$byArticleCode->id]);
});

it('finds attachable products by product title and variant title', function () {
    $byProductTitle = Product::factory()->create(['fulltitle' => ['nl' => 'Uniquely Named Shirt']]);

    $byVariantTitle = Product::factory()->create(['fulltitle' => ['nl' => 'Plain Shirt']]);
    Variant::factory()->for($byVariantTitle)->create(['title' => 'XXL-Special']);

    expect(ProductsRelationManager::attachableProductsQuery('Uniquely Named')->pluck('id')->all())->toBe([$byProductTitle->id])
        ->and(ProductsRelationManager::attachableProductsQuery('XXL-Special')->pluck('id')->all())->toBe([$byVariantTitle->id]);
});
