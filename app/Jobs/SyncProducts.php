<?php

namespace App\Jobs;

use App\Formatters\ProductFormatter;
use App\Models\Product;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use WebshopappApiClient;

class SyncProducts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    private const int PER_PAGE = 250;

    private const array TRANSLATABLES = [
        'title' => 'title',
        'url' => 'url',
        'content' => 'content',
        'fulltitle' => 'fulltitle',
    ];

    public function handle(): void
    {
        $api = $this->createApiClient();
        $defaultLanguage = config('webshop.languages')[0];
        $otherLanguages = array_slice(config('webshop.languages'), 1);

        $syncedIds = $this->syncProducts($api, $defaultLanguage);
        $this->syncTranslations($api, $otherLanguages);

        $this->deleteRemovedProducts($syncedIds);
    }

    /**
     * Stream products from the API one page at a time and persist each page,
     * keeping memory flat regardless of the total number of products.
     *
     * @return int[] The ids of every product returned by the API.
     */
    protected function syncProducts(WebshopappApiClient $api, string $defaultLanguage): array
    {
        $syncedIds = [];
        $pages = (int) ceil($api->products->count() / self::PER_PAGE);

        for ($page = 1; $page <= $pages; $page++) {
            $products = $api->products->get(null, [
                'limit' => self::PER_PAGE,
                'page' => $page,
            ]);

            foreach ($products as $resource) {
                $product = Product::withTrashed()->firstOrNew(['id' => $resource['id']]);

                $formatter = new ProductFormatter($resource, $product);

                foreach (self::TRANSLATABLES as $apiKey => $dbKey) {
                    $formatter->setLanguageAttribute($defaultLanguage, $dbKey, $resource[$apiKey]);
                }

                $product->forceFill([...$formatter->get(), 'deleted_at' => null]);
                $product->save();

                $syncedIds[] = (int) $resource['id'];
            }
        }

        return $syncedIds;
    }

    /**
     * Stream the translatable fields for each additional language one page at
     * a time and merge them into the products persisted by syncProducts().
     *
     * @param  string[]  $languages
     */
    protected function syncTranslations(WebshopappApiClient $api, array $languages): void
    {
        $fields = 'id,'.implode(',', array_keys(self::TRANSLATABLES));
        $pages = (int) ceil($api->products->count() / self::PER_PAGE);

        foreach ($languages as $language) {
            $api->setApiLanguage($language);

            for ($page = 1; $page <= $pages; $page++) {
                $resources = $api->products->get(null, [
                    'limit' => self::PER_PAGE,
                    'fields' => $fields,
                    'page' => $page,
                ]);

                $products = Product::whereKey(array_column($resources, 'id'))->get()->keyBy('id');

                foreach ($resources as $resource) {
                    $product = $products->get($resource['id']);

                    if ($product === null) {
                        continue;
                    }

                    $formatter = new ProductFormatter($resource, $product);

                    foreach (self::TRANSLATABLES as $apiKey => $dbKey) {
                        $formatter->setLanguageAttribute($language, $dbKey, $resource[$apiKey]);
                    }

                    $product->forceFill(Arr::only($formatter->get(), array_values(self::TRANSLATABLES)));
                    $product->save();
                }
            }
        }
    }

    /**
     * Soft-delete any products that no longer exist in the API.
     *
     * @param  int[]  $syncedIds
     */
    protected function deleteRemovedProducts(array $syncedIds): void
    {
        Product::whereNotIn('id', $syncedIds)->delete();
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
