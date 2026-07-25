<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use WebshopappApiClient;

class ProductSyncOverview extends LightspeedCountOverview
{
    protected function label(): string
    {
        return 'Products';
    }

    protected function cacheKey(): string
    {
        return 'lightspeed.count.products';
    }

    protected function databaseCount(): int
    {
        return Product::count();
    }

    protected function lightspeedCount(WebshopappApiClient $api): int
    {
        return $api->products->count();
    }
}
