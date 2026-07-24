<?php

use App\Jobs\Webhooks\InstallVariantWebhooks;
use Illuminate\Support\Facades\Queue;

it('dispatches the install variant webhooks job via artisan command', function () {
    Queue::fake();

    $this->artisan('webhooks:install-variants')
        ->expectsOutput('Dispatching variant webhook installation job...')
        ->expectsOutput('Job dispatched. Variant webhooks will be installed shortly.')
        ->assertSuccessful();

    Queue::assertPushed(InstallVariantWebhooks::class);
});

it('only deletes existing variant webhooks before installing', function () {
    config()->set('webshop.languages', ['nl']);

    $webhooksResource = Mockery::mock();
    $webhooksResource->shouldReceive('get')->once()->andReturn([
        ['id' => 1, 'itemGroup' => 'products'],
        ['id' => 2, 'itemGroup' => 'variants'],
    ]);
    // Only the variant webhook (id 2) should be deleted, product webhooks left intact.
    $webhooksResource->shouldReceive('delete')->once()->with(2);
    $webhooksResource->shouldReceive('create')->times(3);

    $apiClient = Mockery::mock(WebshopappApiClient::class);
    $apiClient->webhooks = $webhooksResource;
    $apiClient->shouldReceive('setApiLanguage')->once()->with('nl');

    foreach ($apiClient->webhooks->get() as $webhook) {
        if (($webhook['itemGroup'] ?? null) === 'variants') {
            $apiClient->webhooks->delete($webhook['id']);
        }
    }

    foreach (config('webshop.languages') as $language) {
        $apiClient->setApiLanguage($language);

        foreach (['created', 'updated', 'deleted'] as $action) {
            $apiClient->webhooks->create([
                'format' => 'json',
                'address' => route("webhooks.variants.{$action}", ['language' => $language]),
                'isActive' => true,
                'itemGroup' => 'variants',
                'itemAction' => $action,
            ]);
        }
    }
});
