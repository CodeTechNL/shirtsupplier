<?php

use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('can set and get a translation', function () {
    $product = Product::factory()->create();

    $product->setTranslation('nl', 'title', 'Nederlandse titel');

    expect($product->getTranslation('nl', 'title'))->toBe('Nederlandse titel');
});

it('returns empty string for missing translation', function () {
    $product = Product::factory()->create();

    expect($product->getTranslation('en', 'title'))->toBe('');
});

it('can set title shortcut', function () {
    $product = Product::factory()->create();

    $product->setTitle('nl', 'Korte titel');

    expect($product->getTranslation('nl', 'title'))->toBe('Korte titel');
});

it('returns translatables array', function () {
    $product = Product::factory()->create();

    expect($product->getTranslatables())->toBe([
        'title' => 'title',
        'url' => 'url',
        'content' => 'content',
        'fulltitle' => 'fulltitle',
    ]);
});
