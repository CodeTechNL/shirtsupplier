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

        $data = $this->downloadProducts($api, $defaultLanguage);
        $data = Arr::keyBy($data, 'id');
        $data = $this->downloadTranslations($api, $data, $otherLanguages);

        $this->persistProducts($data);
        $this->deleteRemovedProducts($data);
    }

    /**
     * Download all products from the API, wrapping translatable fields in language arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function downloadProducts(WebshopappApiClient $api, string $defaultLanguage): array
    {
        $data = [];
        $pages = (int) ceil($api->products->count() / self::PER_PAGE);

        for ($page = 1; $page <= $pages; $page++) {
            $products = $api->products->get(null, [
                'limit' => self::PER_PAGE,
                'page' => $page,
            ]);

            foreach ($products as &$product) {
                foreach (array_keys(self::TRANSLATABLES) as $property) {
                    $product[$property] = [
                        $defaultLanguage => $product[$property],
                    ];
                }
            }

            $data = array_merge($data, $products);
        }

        return $data;
    }

    /**
     * Fetch translations for each additional language and merge them into the data.
     *
     * @param  array<int, array<string, mixed>>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function downloadTranslations(WebshopappApiClient $api, array $data, array $languages): array
    {
        $fields = 'id,' . implode(',', array_keys(self::TRANSLATABLES));
        $pages = (int) ceil($api->products->count() / self::PER_PAGE);

        foreach ($languages as $language) {
            $api->setApiLanguage($language);

            for ($page = 1; $page <= $pages; $page++) {
                $products = $api->products->get(null, [
                    'limit' => self::PER_PAGE,
                    'fields' => $fields,
                    'page' => $page,
                ]);

                foreach ($products as $product) {
                    if (! isset($data[$product['id']])) {
                        continue;
                    }

                    foreach (self::TRANSLATABLES as $apiKey => $dbKey) {
                        $data[$product['id']][$apiKey][$language] = $product[$apiKey];
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Persist each product to the database using the ProductFormatter.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    protected function persistProducts(array $data): void
    {
        foreach ($data as $resource) {
            $product = Product::firstOrNew(['id' => $resource['id']]);

            $formatter = new ProductFormatter($resource, $product);

            foreach (self::TRANSLATABLES as $apiKey => $dbKey) {
                foreach ($resource[$apiKey] as $language => $value) {
                    $formatter->setLanguageAttribute($language, $dbKey, $value);
                }
            }

            $product->forceFill($formatter->get());
            $product->save();
        }
    }

    /**
     * Soft-delete any products that no longer exist in the API.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    protected function deleteRemovedProducts(array $data): void
    {
        Product::whereNotIn('id', array_keys($data))->delete();
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
