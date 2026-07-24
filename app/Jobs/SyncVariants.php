<?php

namespace App\Jobs;

use App\Formatters\VariantFormatter;
use App\Models\Variant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use WebshopappApiClient;

class SyncVariants implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [10, 30, 60];

    private const int PER_PAGE = 250;

    public function handle(): void
    {
        $api = $this->createApiClient();

        $data = $this->downloadVariants($api);
        $data = Arr::keyBy($data, 'id');

        $this->persistVariants($data);
        $this->deleteRemovedVariants($data);
    }

    /**
     * Download all variants from the API.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function downloadVariants(WebshopappApiClient $api): array
    {
        $data = [];
        $pages = (int) ceil($api->variants->count() / self::PER_PAGE);

        for ($page = 1; $page <= $pages; $page++) {
            $variants = $api->variants->get(null, [
                'limit' => self::PER_PAGE,
                'page' => $page,
            ]);

            $data = array_merge($data, $variants);
        }

        return $data;
    }

    /**
     * Persist each variant to the database using the VariantFormatter.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    protected function persistVariants(array $data): void
    {
        foreach ($data as $resource) {
            $variant = Variant::firstOrNew(['id' => $resource['id']]);

            $variant->forceFill((new VariantFormatter($resource))->get());
            $variant->save();
        }
    }

    /**
     * Soft-delete any variants that no longer exist in the API.
     *
     * @param  array<int, array<string, mixed>>  $data
     */
    protected function deleteRemovedVariants(array $data): void
    {
        Variant::whereNotIn('id', array_keys($data))->delete();
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
