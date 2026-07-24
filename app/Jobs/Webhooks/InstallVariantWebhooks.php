<?php

namespace App\Jobs\Webhooks;

use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use WebshopappApiClient;

class InstallVariantWebhooks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [5, 15, 30];

    private const string ITEM_GROUP = 'variants';

    private const array ACTIONS = ['created', 'updated', 'deleted'];

    public function handle(): void
    {
        $api = new WebshopappApiClient(
            config('webshop.lightspeed.cluster'),
            config('webshop.lightspeed.key'),
            config('webshop.lightspeed.secret'),
            config('webshop.lightspeed.language'),
        );

        foreach ($api->webhooks->get() as $webhook) {
            if (($webhook['itemGroup'] ?? null) === self::ITEM_GROUP) {
                $api->webhooks->delete($webhook['id']);
            }
        }

        foreach (config('webshop.languages') as $language) {
            $api->setApiLanguage($language);

            foreach (self::ACTIONS as $action) {
                $api->webhooks->create([
                    'format' => 'json',
                    'address' => route("webhooks.variants.{$action}", ['language' => $language]),
                    'isActive' => true,
                    'itemGroup' => self::ITEM_GROUP,
                    'itemAction' => $action,
                ]);
            }
        }

        Webhook::syncFromLightspeed($api);
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);
    }
}
