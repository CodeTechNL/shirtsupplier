<?php

namespace App\Jobs\Webhooks;

use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use WebshopappApiClient;

class InstallWebhooks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [5, 15, 30];

    /**
     * @param  array<int, array{group: string, action: string, language: string}>  $hooks
     */
    public function __construct(public array $hooks) {}

    public function handle(): void
    {
        $api = app(WebshopappApiClient::class);

        foreach (collect($this->hooks)->groupBy('language') as $language => $hooks) {
            $api->setApiLanguage($language);

            foreach ($hooks as $hook) {
                $api->webhooks->create([
                    'format' => 'json',
                    'address' => route("webhooks.{$hook['group']}.{$hook['action']}", ['language' => $hook['language']]),
                    'isActive' => true,
                    'itemGroup' => $hook['group'],
                    'itemAction' => $hook['action'],
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
