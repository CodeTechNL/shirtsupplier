<?php

use App\Filament\Resources\Webhooks\Pages\ListWebhooks;
use App\Jobs\Webhooks\InstallWebhooks;
use App\Jobs\Webhooks\SyncWebhooks;
use App\Models\User;
use App\Models\Webhook;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

function fakeApiForDeleting(): MockInterface
{
    $webhooksResource = Mockery::mock();

    $api = Mockery::mock(WebshopappApiClient::class);
    $api->webhooks = $webhooksResource;

    app()->instance(WebshopappApiClient::class, $api);

    return $webhooksResource;
}

it('lists all webhooks', function () {
    $webhooks = Webhook::factory()->count(4)->create();

    Livewire::test(ListWebhooks::class)
        ->assertOk()
        ->assertCanSeeTableRecords($webhooks);
});

it('dispatches the sync job from the header action', function () {
    Queue::fake();

    Livewire::test(ListWebhooks::class)
        ->callAction('syncWebhooks')
        ->assertNotified();

    Queue::assertPushed(SyncWebhooks::class);
});

it('dispatches an install job for the individually selected webhooks', function () {
    Queue::fake();

    Livewire::test(ListWebhooks::class)
        ->callAction('installWebhooks', data: ['hooks' => ['products|created|nl', 'variants|deleted|nl']])
        ->assertNotified();

    Queue::assertPushed(InstallWebhooks::class, fn (InstallWebhooks $job): bool => $job->hooks === [
        ['group' => 'products', 'action' => 'created', 'language' => 'nl'],
        ['group' => 'variants', 'action' => 'deleted', 'language' => 'nl'],
    ]);
});

it('only installs webhooks that are not yet installed', function () {
    Queue::fake();

    Webhook::factory()->create(['item_group' => 'products', 'item_action' => 'created', 'language' => 'nl']);

    Livewire::test(ListWebhooks::class)
        ->callAction('installWebhooks', data: ['hooks' => ['products|created|nl', 'products|updated|nl']])
        ->assertNotified();

    Queue::assertPushed(InstallWebhooks::class, fn (InstallWebhooks $job): bool => $job->hooks === [
        ['group' => 'products', 'action' => 'updated', 'language' => 'nl'],
    ]);
});

it('does not dispatch anything when only installed webhooks are selected', function () {
    Queue::fake();

    Webhook::factory()->create(['item_group' => 'products', 'item_action' => 'created', 'language' => 'nl']);

    Livewire::test(ListWebhooks::class)
        ->callAction('installWebhooks', data: ['hooks' => ['products|created|nl']])
        ->assertNotified('No new webhooks selected.');

    Queue::assertNothingPushed();
});

it('pre-checks installed webhooks in the install modal', function () {
    Webhook::factory()->create(['item_group' => 'variants', 'item_action' => 'deleted', 'language' => 'nl']);

    Livewire::test(ListWebhooks::class)
        ->mountAction('installWebhooks')
        ->assertSchemaStateSet(['hooks' => ['variants|deleted|nl']]);
});

it('rejects unknown webhook keys', function () {
    Queue::fake();

    Livewire::test(ListWebhooks::class)
        ->callAction('installWebhooks', data: ['hooks' => ['customers|created|nl']])
        ->assertHasFormErrors(['hooks.0']);

    Queue::assertNothingPushed();
});

it('deletes a webhook locally and at Lightspeed', function () {
    $webhook = Webhook::factory()->create(['lightspeed_id' => 123]);

    fakeApiForDeleting()->shouldReceive('delete')->once()->with(123);

    Livewire::test(ListWebhooks::class)
        ->callAction(TestAction::make('delete')->table($webhook))
        ->assertNotified();

    expect(Webhook::query()->whereKey($webhook->getKey())->exists())->toBeFalse();
});

it('deletes a webhook without a lightspeed id locally only', function () {
    $webhook = Webhook::factory()->create(['lightspeed_id' => null]);

    fakeApiForDeleting()->shouldNotReceive('delete');

    Livewire::test(ListWebhooks::class)
        ->callAction(TestAction::make('delete')->table($webhook))
        ->assertNotified();

    expect(Webhook::query()->whereKey($webhook->getKey())->exists())->toBeFalse();
});

it('keeps the local webhook when the Lightspeed delete fails', function () {
    $webhook = Webhook::factory()->create(['lightspeed_id' => 123]);

    fakeApiForDeleting()->shouldReceive('delete')->once()->with(123)->andThrow(new RuntimeException('Lightspeed is down'));

    Livewire::test(ListWebhooks::class)
        ->callAction(TestAction::make('delete')->table($webhook))
        ->assertNotified('Could not delete the webhook at Lightspeed.');

    expect(Webhook::query()->whereKey($webhook->getKey())->exists())->toBeTrue();
});

it('bulk deletes webhooks locally and at Lightspeed', function () {
    $webhooks = Webhook::factory()->count(3)->create();

    $api = fakeApiForDeleting();

    foreach ($webhooks as $webhook) {
        $api->shouldReceive('delete')->once()->with($webhook->lightspeed_id);
    }

    Livewire::test(ListWebhooks::class)
        ->selectTableRecords($webhooks->pluck('id')->all())
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertNotified('Deleted 3 webhooks.');

    expect(Webhook::count())->toBe(0);
});

it('keeps webhooks whose Lightspeed delete fails during a bulk delete', function () {
    $failing = Webhook::factory()->create(['lightspeed_id' => 123]);
    $succeeding = Webhook::factory()->create(['lightspeed_id' => 456]);

    $api = fakeApiForDeleting();
    $api->shouldReceive('delete')->once()->with(123)->andThrow(new RuntimeException('Lightspeed is down'));
    $api->shouldReceive('delete')->once()->with(456);

    Livewire::test(ListWebhooks::class)
        ->selectTableRecords([$failing->id, $succeeding->id])
        ->callAction(TestAction::make('delete')->table()->bulk())
        ->assertNotified('Deleted 1 of 2 webhooks.');

    expect(Webhook::query()->whereKey($failing->getKey())->exists())->toBeTrue()
        ->and(Webhook::query()->whereKey($succeeding->getKey())->exists())->toBeFalse();
});
