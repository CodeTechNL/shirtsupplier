<?php

namespace App\Filament\Widgets;

use App\Models\Variant;
use WebshopappApiClient;

class VariantSyncOverview extends LightspeedCountOverview
{
    protected function label(): string
    {
        return 'Variants';
    }

    protected function cacheKey(): string
    {
        return 'lightspeed.count.variants';
    }

    protected function databaseCount(): int
    {
        return Variant::count();
    }

    protected function lightspeedCount(WebshopappApiClient $api): int
    {
        return $api->variants->count();
    }
}
