<?php

use App\Jobs\SyncProducts;
use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

it('dispatches the sync products job via artisan command', function () {
    Queue::fake();

    $this->artisan('products:sync')
        ->expectsOutput('Dispatching product sync job...')
        ->expectsOutput('Job dispatched. Products will be synced shortly.')
        ->assertSuccessful();

    Queue::assertPushed(SyncProducts::class);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function apiProduct(int $id, string $title, array $overrides = []): array
{
    return [
        'id' => $id,
        'title' => $title,
        'url' => str($title)->slug()->toString(),
        'fulltitle' => "{$title} Full",
        'content' => "{$title} content",
        'isVisible' => true,
        'image' => ['src' => "https://example.com/{$id}.jpg"],
        ...$overrides,
    ];
}

it('syncs products from the api page by page', function () {
    $productsResource = Mockery::mock();
    $productsResource->shouldReceive('count')->andReturn(251);
    $productsResource->shouldReceive('get')
        ->with(null, Mockery::on(fn ($params) => $params['page'] === 1 && $params['limit'] === 250 && ! isset($params['fields'])))
        ->once()
        ->andReturn([apiProduct(101, 'First Product')]);
    $productsResource->shouldReceive('get')
        ->with(null, Mockery::on(fn ($params) => $params['page'] === 2 && $params['limit'] === 250 && ! isset($params['fields'])))
        ->once()
        ->andReturn([apiProduct(102, 'Second Product', ['isVisible' => false])]);

    $apiClient = Mockery::mock(WebshopappApiClient::class);
    $apiClient->products = $productsResource;

    $job = new SyncProducts;

    $syncedIds = (new ReflectionMethod($job, 'syncProducts'))->invoke($job, $apiClient, 'nl');

    expect($syncedIds)->toBe([101, 102])
        ->and(Product::find(101)->getTranslation('nl', 'title'))->toBe('First Product')
        ->and(Product::find(101)->image)->toBe(['src' => 'https://example.com/101.jpg'])
        ->and(Product::find(102)->getTranslation('nl', 'fulltitle'))->toBe('Second Product Full')
        ->and(Product::find(102)->is_visible)->toBeFalse();
});

it('updates an existing product and keeps its other translations', function () {
    Product::factory()->create([
        'id' => 101,
        'title' => ['nl' => 'Old Title', 'en' => 'English Title'],
    ]);

    $productsResource = Mockery::mock();
    $productsResource->shouldReceive('count')->andReturn(1);
    $productsResource->shouldReceive('get')->andReturn([apiProduct(101, 'New Title')]);

    $apiClient = Mockery::mock(WebshopappApiClient::class);
    $apiClient->products = $productsResource;

    $job = new SyncProducts;

    (new ReflectionMethod($job, 'syncProducts'))->invoke($job, $apiClient, 'nl');

    expect(Product::count())->toBe(1)
        ->and(Product::find(101)->title)->toBe(['nl' => 'New Title', 'en' => 'English Title']);
});

it('restores a soft-deleted product that reappears in the api', function () {
    $product = Product::factory()->create(['id' => 101]);
    $product->delete();

    $productsResource = Mockery::mock();
    $productsResource->shouldReceive('count')->andReturn(1);
    $productsResource->shouldReceive('get')->andReturn([apiProduct(101, 'Back Again')]);

    $apiClient = Mockery::mock(WebshopappApiClient::class);
    $apiClient->products = $productsResource;

    $job = new SyncProducts;

    (new ReflectionMethod($job, 'syncProducts'))->invoke($job, $apiClient, 'nl');

    expect(Product::find(101))->not->toBeNull()
        ->and(Product::find(101)->getTranslation('nl', 'title'))->toBe('Back Again');
});

it('merges translations for additional languages without touching other fields', function () {
    Product::factory()->create([
        'id' => 101,
        'title' => ['nl' => 'Dutch Title'],
        'is_visible' => false,
        'image' => ['src' => 'https://example.com/101.jpg'],
    ]);

    $productsResource = Mockery::mock();
    $productsResource->shouldReceive('count')->andReturn(1);
    $productsResource->shouldReceive('get')
        ->with(null, Mockery::on(fn ($params) => $params['page'] === 1 && $params['fields'] === 'id,title,url,content,fulltitle'))
        ->andReturn([
            ['id' => 101, 'title' => 'English Title', 'url' => 'english-title', 'content' => 'English content', 'fulltitle' => 'English Full'],
            ['id' => 999, 'title' => 'Unknown', 'url' => 'unknown', 'content' => null, 'fulltitle' => 'Unknown'],
        ]);

    $apiClient = Mockery::mock(WebshopappApiClient::class);
    $apiClient->products = $productsResource;
    $apiClient->shouldReceive('setApiLanguage')->with('en')->once();

    $job = new SyncProducts;

    (new ReflectionMethod($job, 'syncTranslations'))->invoke($job, $apiClient, ['en']);

    $product = Product::find(101);

    expect($product->title)->toBe(['nl' => 'Dutch Title', 'en' => 'English Title'])
        ->and($product->getTranslation('en', 'fulltitle'))->toBe('English Full')
        ->and($product->is_visible)->toBeFalse()
        ->and($product->image)->toBe(['src' => 'https://example.com/101.jpg'])
        ->and(Product::withTrashed()->find(999))->toBeNull();
});

it('soft-deletes products no longer in the api', function () {
    $existing = Product::factory()->create();
    $synced = Product::factory()->create();

    $job = new SyncProducts;

    (new ReflectionMethod($job, 'deleteRemovedProducts'))->invoke($job, [$synced->id]);

    expect(Product::find($existing->id))->toBeNull()
        ->and(Product::withTrashed()->find($existing->id))->not->toBeNull()
        ->and(Product::find($synced->id))->not->toBeNull();
});
