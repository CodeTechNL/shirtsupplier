<?php

namespace App\Jobs;

use App\Formatters\VariantFormatter;
use App\Models\Variant;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use WebshopappApiClient;

class SyncVariants implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    private const int PER_PAGE = 250;

    /**
     * Columns updated when a variant already exists during upsert.
     *
     * @var string[]
     */
    private const array UPSERT_COLUMNS = [
        'product_id', 'title', 'sku', 'ean', 'article_code', 'is_default',
        'sort_order', 'price_excl', 'price_incl', 'old_price_excl',
        'old_price_incl', 'stock_tracking', 'stock_level', 'weight', 'image',
        'deleted_at', 'updated_at',
    ];

    public function handle(): void
    {
        $api = $this->createApiClient();
        $syncedAt = now();

        $this->syncVariants($api, $syncedAt);
        $this->deleteRemovedVariants($syncedAt);
    }

    /**
     * Stream variants from the API one page at a time and upsert each page,
     * keeping memory flat regardless of the total number of variants.
     */
    protected function syncVariants(WebshopappApiClient $api, CarbonInterface $syncedAt): void
    {
        $pages = (int) ceil($api->variants->count() / self::PER_PAGE);

        for ($page = 1; $page <= $pages; $page++) {
            $variants = $api->variants->get(null, [
                'limit' => self::PER_PAGE,
                'page' => $page,
            ]);

            $rows = [];

            foreach ($variants as $variant) {
                $attributes = (new VariantFormatter($variant))->get();
                $attributes['image'] = isset($attributes['image']) ? json_encode($attributes['image']) : null;

                $rows[] = [
                    'id' => $variant['id'],
                    ...$attributes,
                    'deleted_at' => null,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];
            }

            if ($rows !== []) {
                Variant::upsert($rows, ['id'], self::UPSERT_COLUMNS);
            }
        }
    }

    /**
     * Soft-delete any variants that were not touched by this sync run. Every
     * synced variant had its updated_at stamped with $syncedAt, so anything
     * older no longer exists in the API.
     */
    protected function deleteRemovedVariants(CarbonInterface $syncedAt): void
    {
        Variant::query()
            ->where('updated_at', '<', $syncedAt)
            ->delete();
    }

    protected function createApiClient(): WebshopappApiClient
    {
        return new WebshopappApiClient(
            config('webshop.lightspeed.cluster'),
            config('webshop.lightspeed.key'),
            config('webshop.lightspeed.secret'),
            config('webshop.lightspeed.language'),
        );
    }

    public function failed(\Throwable $exception): void
    {
        report($exception);
    }
}
