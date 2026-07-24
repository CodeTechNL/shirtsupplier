<?php

namespace App\Models;

use Database\Factories\WebhookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use WebshopappApiClient;

class Webhook extends Model
{
    /** @use HasFactory<WebhookFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'lightspeed_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Mirror the webhooks currently installed at Lightspeed into the local table,
     * removing any local rows that no longer exist remotely.
     */
    public static function syncFromLightspeed(WebshopappApiClient $api): void
    {
        $remoteIds = [];

        foreach ($api->webhooks->get() as $webhook) {
            $remoteIds[] = $webhook['id'];

            static::updateOrCreate(
                ['lightspeed_id' => $webhook['id']],
                [
                    'item_group' => $webhook['itemGroup'] ?? '',
                    'item_action' => $webhook['itemAction'] ?? '',
                    'language' => $webhook['language'] ?? config('webshop.lightspeed.language'),
                    'address' => $webhook['address'] ?? '',
                    'format' => $webhook['format'] ?? 'json',
                    'is_active' => (bool) ($webhook['isActive'] ?? false),
                ],
            );
        }

        static::whereNotIn('lightspeed_id', $remoteIds)->delete();
    }
}
