<?php

use App\Filament\Resources\Webhooks\Pages\ListWebhooks;
use App\Jobs\Webhooks\InstallProductWebhooks;
use App\Jobs\Webhooks\InstallVariantWebhooks;
use App\Jobs\Webhooks\SyncWebhooks;
use App\Models\User;
use App\Models\Webhook;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

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

it('dispatches the product webhook install job', function () {
    Queue::fake();

    Livewire::test(ListWebhooks::class)
        ->callAction('installProductWebhooks')
        ->assertNotified();

    Queue::assertPushed(InstallProductWebhooks::class);
});

it('dispatches the variant webhook install job', function () {
    Queue::fake();

    Livewire::test(ListWebhooks::class)
        ->callAction('installVariantWebhooks')
        ->assertNotified();

    Queue::assertPushed(InstallVariantWebhooks::class);
});
