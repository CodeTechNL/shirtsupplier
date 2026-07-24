<?php

namespace App\Jobs\Webhooks;

use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use WebshopappApiClient;

class SyncWebhooks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [5, 15, 30];

    public function handle(): void
    {
        $api = new WebshopappApiClient(
            config('webshop.lightspeed.cluster'),
            config('webshop.lightspeed.key'),
            config('webshop.lightspeed.secret'),
            config('webshop.lightspeed.language'),
        );

        Webhook::syncFromLightspeed($api);
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);
    }
}
