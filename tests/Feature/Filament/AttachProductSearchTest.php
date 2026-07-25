<?php

use App\Filament\Resources\SameProductGroups\Pages\EditSameProductGroup;
use App\Filament\Resources\SameProductGroups\RelationManagers\ProductsRelationManager;
use App\Models\Product;
use App\Models\SameProductGroup;
use App\Models\User;
use App\Models\Variant;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(fn () => actingAs(User::factory()->create()));

/**
 * A Livewire test instance of the products relation manager for a group.
 */
function productsRelationManager(SameProductGroup $group): Testable
{
    return Livewire::test(ProductsRelationManager::class, [
        'ownerRecord' => $group,
        'pageClass' => EditSameProductGroup::class,
    ]);
}

it('builds attach table rows enriched with variant identifiers and visibility', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create(['fulltitle' => ['nl' => 'Plain Tee'], 'is_visible' => true]);
    Variant::factory()->for($product)->create(['sku' => 'FINDME-SKU', 'ean' => '5099206084247']);

    $rows = ProductsRelationManager::attachableProductRows('FINDME-SKU', $group);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray(['id' => $product->id, 'visible' => true])
        ->and($rows[0]['title'])->toContain('Plain Tee')
        ->and($rows[0]['codes'])->toContain('FINDME-SKU')
        ->and($rows[0]['codes'])->toContain('5099206084247');
});

it('finds attach rows by sku, ean or article code', function () {
    $group = SameProductGroup::factory()->create();

    $bySku = Product::factory()->create();
    Variant::factory()->for($bySku)->create(['sku' => 'SKU-ABC', 'ean' => '', 'article_code' => '']);

    $byEan = Product::factory()->create();
    Variant::factory()->for($byEan)->create(['sku' => '', 'ean' => '5099206084247', 'article_code' => '']);

    $byArticleCode = Product::factory()->create();
    Variant::factory()->for($byArticleCode)->create(['sku' => '', 'ean' => '', 'article_code' => 'ART-XYZ']);

    expect(array_column(ProductsRelationManager::attachableProductRows('SKU-ABC', $group), 'id'))->toBe([$bySku->id])
        ->and(array_column(ProductsRelationManager::attachableProductRows('5099206084247', $group), 'id'))->toBe([$byEan->id])
        ->and(array_column(ProductsRelationManager::attachableProductRows('ART-XYZ', $group), 'id'))->toBe([$byArticleCode->id]);
});

it('excludes products already in the current group from the attach table', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['sku' => 'FINDME-SKU']);
    $group->products()->attach($product);

    expect(ProductsRelationManager::attachableProductRows('FINDME-SKU', $group))->toBe([]);
});

it('still lists products that belong to a different group', function () {
    $group = SameProductGroup::factory()->create();
    $product = Product::factory()->create();
    Variant::factory()->for($product)->create(['sku' => 'FINDME-SKU']);
    SameProductGroup::factory()->create()->products()->attach($product);

    expect(array_column(ProductsRelationManager::attachableProductRows('FINDME-SKU', $group), 'id'))
        ->toBe([$product->id]);
});

it('configures the attach search as a search input opted out of password autofill', function () {
    $field = (new ReflectionMethod(ProductsRelationManager::class, 'attachSearchField'))
        ->invoke(null);

    expect($field->getType())->toBe('search')
        ->and($field->getAutocomplete())->toBe('off')
        ->and($field->getExtraInputAttributes())->toMatchArray([
            'data-1p-ignore' => 'true',
            'data-lpignore' => 'true',
            'data-bwignore' => 'true',
        ]);
});

it('attaches the selected products through the modal action', function () {
    $group = SameProductGroup::factory()->create();
    $products = Product::factory()->count(2)->create();

    productsRelationManager($group)
        ->callAction(TestAction::make('attach')->table(), ['recordIds' => $products->pluck('id')->all()])
        ->assertNotified();

    expect($group->products()->pluck('products.id')->all())
        ->toEqualCanonicalizing($products->pluck('id')->all());
});

it('warns and attaches nothing when no product is selected', function () {
    $group = SameProductGroup::factory()->create();

    productsRelationManager($group)
        ->callAction(TestAction::make('attach')->table(), ['recordIds' => []])
        ->assertNotified();

    expect($group->products()->count())->toBe(0);
});
