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
                    'language' => static::normalizeLanguage($webhook['language'] ?? null),
                    'address' => $webhook['address'] ?? '',
                    'format' => $webhook['format'] ?? 'json',
                    'is_active' => (bool) ($webhook['isActive'] ?? false),
                ],
            );
        }

        static::whereNotIn('lightspeed_id', $remoteIds)->delete();
    }

    /**
     * Coerce Lightspeed's language field into a scalar code.
     *
     * The API returns a plain code (e.g. "nl") for language-scoped webhooks,
     * but a nested resource array (or an empty array for unscoped webhooks)
     * for others. Fall back to the configured default when no code is present.
     *
     * @param  array<string, mixed>|string|null  $language
     */
    protected static function normalizeLanguage(array|string|null $language): string
    {
        if (is_string($language) && $language !== '') {
            return $language;
        }

        if (is_array($language) && is_string($language['code'] ?? null) && $language['code'] !== '') {
            return $language['code'];
        }

        return config('webshop.lightspeed.language');
    }
}
