<?php

use App\Jobs\Webhooks\InstallWebhooks;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\assertDatabaseHas;

uses(LazilyRefreshDatabase::class);

it('creates the given hooks at Lightspeed and syncs the result', function () {
    $webhooksResource = Mockery::mock();
    $webhooksResource->shouldReceive('create')->once()->with([
        'format' => 'json',
        'address' => route('webhooks.products.created', ['language' => 'nl']),
        'isActive' => true,
        'itemGroup' => 'products',
        'itemAction' => 'created',
    ]);
    $webhooksResource->shouldReceive('create')->once()->with([
        'format' => 'json',
        'address' => route('webhooks.variants.deleted', ['language' => 'nl']),
        'isActive' => true,
        'itemGroup' => 'variants',
        'itemAction' => 'deleted',
    ]);
    $webhooksResource->shouldReceive('get')->once()->andReturn([
        [
            'id' => 10,
            'itemGroup' => 'products',
            'itemAction' => 'created',
            'language' => 'nl',
            'address' => route('webhooks.products.created', ['language' => 'nl']),
            'format' => 'json',
            'isActive' => true,
        ],
        [
            'id' => 11,
            'itemGroup' => 'variants',
            'itemAction' => 'deleted',
            'language' => 'nl',
            'address' => route('webhooks.variants.deleted', ['language' => 'nl']),
            'format' => 'json',
            'isActive' => true,
        ],
    ]);

    $api = Mockery::mock(WebshopappApiClient::class);
    $api->webhooks = $webhooksResource;
    $api->shouldReceive('setApiLanguage')->once()->with('nl');

    app()->instance(WebshopappApiClient::class, $api);

    (new InstallWebhooks([
        ['group' => 'products', 'action' => 'created', 'language' => 'nl'],
        ['group' => 'variants', 'action' => 'deleted', 'language' => 'nl'],
    ]))->handle();

    assertDatabaseHas('webhooks', ['lightspeed_id' => 10, 'item_group' => 'products', 'item_action' => 'created']);
    assertDatabaseHas('webhooks', ['lightspeed_id' => 11, 'item_group' => 'variants', 'item_action' => 'deleted']);
});

it('sets the api language per hook language before creating', function () {
    config()->set('webshop.languages', ['nl', 'en']);

    $webhooksResource = Mockery::mock();
    $webhooksResource->shouldReceive('create')->twice();
    $webhooksResource->shouldReceive('get')->once()->andReturn([]);

    $api = Mockery::mock(WebshopappApiClient::class);
    $api->webhooks = $webhooksResource;
    $api->shouldReceive('setApiLanguage')->once()->with('nl');
    $api->shouldReceive('setApiLanguage')->once()->with('en');

    app()->instance(WebshopappApiClient::class, $api);

    (new InstallWebhooks([
        ['group' => 'products', 'action' => 'created', 'language' => 'nl'],
        ['group' => 'products', 'action' => 'created', 'language' => 'en'],
    ]))->handle();
});
