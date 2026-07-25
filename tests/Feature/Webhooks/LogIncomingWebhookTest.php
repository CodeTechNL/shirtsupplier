<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

uses(LazilyRefreshDatabase::class);

it('logs every incoming product webhook', function (string $routeName) {
    Queue::fake();
    Log::spy();

    $this->post(route($routeName, ['language' => 'nl']), ['resource_id' => 123])
        ->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(function (string $message, array $context) use ($routeName) {
            return $message === 'Incoming webhook received'
                && $context['route'] === $routeName
                && $context['language'] === 'nl'
                && $context['payload'] === ['resource_id' => 123];
        });
})->with([
    'webhooks.products.created',
    'webhooks.products.updated',
    'webhooks.products.deleted',
    'webhooks.variants.created',
    'webhooks.variants.updated',
    'webhooks.variants.deleted',
]);

it('logs the resource id headers sent by Lightspeed', function () {
    Log::spy();

    $this->withHeaders(['x-product-id' => '456'])
        ->post(route('webhooks.products.deleted', ['language' => 'nl']), [])
        ->assertSuccessful();

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(fn (string $message, array $context) => $context['product_id'] === '456');
});
