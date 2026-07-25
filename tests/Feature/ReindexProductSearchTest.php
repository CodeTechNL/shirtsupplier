<?php

use App\Jobs\ReindexProductSearch;
use App\Jobs\Webhooks\StoreOrUpdateProduct;
use App\Models\Product;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\Jobs\MakeSearchable;

uses(LazilyRefreshDatabase::class);

it('schedules a search reindex two minutes after a product webhook is processed', function () {
    $this->freezeTime();
    Queue::fake([ReindexProductSearch::class]);

    (new StoreOrUpdateProduct(['id' => 4321, 'title' => 'Plain Tee'], 'nl'))->handle();

    Queue::assertPushed(
        ReindexProductSearch::class,
        fn (ReindexProductSearch $job): bool => $job->delay?->equalTo(now()->addMinutes(2)) ?? false,
    );
});

it('makes the product searchable again when the reindex job runs', function () {
    $product = Product::factory()->create();

    config(['scout.queue' => true]);
    Queue::fake([MakeSearchable::class]);

    (new ReindexProductSearch($product->id))->handle();

    Queue::assertPushed(
        MakeSearchable::class,
        fn (MakeSearchable $job): bool => $job->models->first()->is($product),
    );
});

it('does nothing when the product was deleted before the reindex ran', function () {
    $product = Product::factory()->create();
    $product->delete();

    config(['scout.queue' => true]);
    Queue::fake([MakeSearchable::class]);

    (new ReindexProductSearch($product->id))->handle();

    Queue::assertNotPushed(MakeSearchable::class);
});
