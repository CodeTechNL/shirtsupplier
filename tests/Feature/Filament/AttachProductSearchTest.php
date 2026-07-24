<?php

use App\Filament\Resources\SameProductGroups\Pages\EditSameProductGroup;
use App\Filament\Resources\SameProductGroups\RelationManagers\ProductsRelationManager;
use App\Models\Product;
use App\Models\SameProductGroup;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => $this->actingAs(User::factory()->create()));

/**
 * Search the attach action's product select exactly the way the modal does,
 * returning the [id => label] results.
 *
 * @return array<int, string>
 */
function searchAttachableProducts(SameProductGroup $group, string $search): array
{
    $component = Livewire\Livewire::test(ProductsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditSameProductGroup::class,
    ])->mountTableAction('attach');

    $select = collect($component->instance()->getMountedTableActionForm()->getFlatComponents(withHidden: true))
        ->first(fn ($field) => method_exists($field, 'getName') && $field->getName() === 'recordId');

    return $select->getSearchResults($search);
}

it('finds a product in the attach modal by its variant sku', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['sku' => 'FINDME-SKU']);

    expect(searchAttachableProducts($group, 'FINDME-SKU'))->toHaveKey($product->id);
});

it('finds a product in the attach modal by its variant ean', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['ean' => '5099206084247']);

    expect(searchAttachableProducts($group, '5099206084247'))->toHaveKey($product->id);
});

it('finds a product in the attach modal by its variant article code', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['article_code' => 'ART-XYZ']);

    expect(searchAttachableProducts($group, 'ART-XYZ'))->toHaveKey($product->id);
});

it('shows the matched variant identifiers in the result label', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create(['fulltitle' => ['nl' => 'Plain Tee']]);
    Variant::factory()->for($product)->create(['sku' => 'FINDME-SKU']);

    $label = searchAttachableProducts($group, 'FINDME-SKU')[$product->id];

    expect($label)->toContain('Plain Tee')
        ->and($label)->toContain('FINDME-SKU');
});

it('excludes products already in the current group from the attach search', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['sku' => 'FINDME-SKU']);
    $group->products()->attach($product);

    expect(searchAttachableProducts($group, 'FINDME-SKU'))->not->toHaveKey($product->id);
});

it('still finds products that belong to a different group (multiple groups allowed)', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['sku' => 'FINDME-SKU']);
    SameProductGroup::factory()->create()->products()->attach($product);

    expect(searchAttachableProducts($group, 'FINDME-SKU'))->toHaveKey($product->id);
});
