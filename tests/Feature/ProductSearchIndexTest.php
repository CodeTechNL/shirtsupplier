<?php

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

it('enriches the search record with the variant identifiers', function () {
    $product = Product::factory()->create(['fulltitle' => ['nl' => 'Plain Tee']]);
    Variant::factory()->for($product)->create([
        'sku' => 'SKU-1',
        'ean' => '5099206084247',
        'article_code' => 'ART-9',
        'title' => 'XXL',
    ]);

    $record = $product->toSearchableArray();

    expect($record)
        ->toMatchArray([
            'id' => $product->id,
            'fulltitle' => 'Plain Tee',
        ])
        ->and($record['skus'])->toContain('SKU-1')
        ->and($record['eans'])->toContain('5099206084247')
        ->and($record['article_codes'])->toContain('ART-9')
        ->and($record['variant_titles'])->toContain('XXL');
});

it('eager loads variants for a chunk instead of querying per product', function () {
    Product::factory()->count(3)->create()->each(
        fn (Product $product) => Variant::factory()->count(2)->for($product)->create(),
    );

    $products = Product::query()->get();

    DB::enableQueryLog();
    $prepared = $products->first()->makeSearchableUsing($products);
    DB::disableQueryLog();

    expect($prepared->first()->relationLoaded('variants'))->toBeTrue()
        ->and($prepared->every(fn (Product $product): bool => $product->relationLoaded('variants')))->toBeTrue()
        ->and(DB::getQueryLog())->toHaveCount(1);
});
