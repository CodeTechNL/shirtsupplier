<?php

use App\Models\Webhook;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(LazilyRefreshDatabase::class);

function fakeApiReturningWebhooks(array $webhooks): WebshopappApiClient
{
    $webhooksResource = Mockery::mock();
    $webhooksResource->shouldReceive('get')->once()->andReturn($webhooks);

    $api = Mockery::mock(WebshopappApiClient::class);
    $api->webhooks = $webhooksResource;

    return $api;
}

it('mirrors remote webhooks into the local table', function () {
    $api = fakeApiReturningWebhooks([
        [
            'id' => 10,
            'itemGroup' => 'products',
            'itemAction' => 'created',
            'language' => 'nl',
            'address' => 'https://shop.test/webhooks/nl/products/created',
            'format' => 'json',
            'isActive' => true,
        ],
        [
            'id' => 11,
            'itemGroup' => 'variants',
            'itemAction' => 'deleted',
            'language' => 'nl',
            'address' => 'https://shop.test/webhooks/nl/variants/deleted',
            'format' => 'json',
            'isActive' => false,
        ],
    ]);

    Webhook::syncFromLightspeed($api);

    expect(Webhook::count())->toBe(2);
    assertDatabaseHas('webhooks', [
        'lightspeed_id' => 10,
        'item_group' => 'products',
        'item_action' => 'created',
        'is_active' => true,
    ]);
    assertDatabaseHas('webhooks', [
        'lightspeed_id' => 11,
        'item_group' => 'variants',
        'item_action' => 'deleted',
        'is_active' => false,
    ]);
});

it('updates an existing local webhook by its lightspeed id', function () {
    Webhook::factory()->create([
        'lightspeed_id' => 10,
        'item_group' => 'products',
        'item_action' => 'created',
        'is_active' => false,
    ]);

    $api = fakeApiReturningWebhooks([
        [
            'id' => 10,
            'itemGroup' => 'products',
            'itemAction' => 'created',
            'language' => 'nl',
            'address' => 'https://shop.test/webhooks/nl/products/created',
            'format' => 'json',
            'isActive' => true,
        ],
    ]);

    Webhook::syncFromLightspeed($api);

    expect(Webhook::count())->toBe(1);
    assertDatabaseHas('webhooks', ['lightspeed_id' => 10, 'is_active' => true]);
});

it('prunes local webhooks that no longer exist remotely', function () {
    Webhook::factory()->create(['lightspeed_id' => 999]);

    $api = fakeApiReturningWebhooks([
        [
            'id' => 10,
            'itemGroup' => 'products',
            'itemAction' => 'created',
            'language' => 'nl',
            'address' => 'https://shop.test/webhooks/nl/products/created',
            'format' => 'json',
            'isActive' => true,
        ],
    ]);

    Webhook::syncFromLightspeed($api);

    assertDatabaseMissing('webhooks', ['lightspeed_id' => 999]);
    assertDatabaseHas('webhooks', ['lightspeed_id' => 10]);
});
