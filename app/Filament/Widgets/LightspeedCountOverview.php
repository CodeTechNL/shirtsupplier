<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Throwable;
use WebshopappApiClient;

abstract class LightspeedCountOverview extends BaseWidget
{
    protected ?string $pollingInterval = null;

    /**
     * Number of seconds the live Lightspeed count is cached for.
     */
    private const int CACHE_TTL = 900;

    /**
     * The label shown above the stat, e.g. "Products".
     */
    abstract protected function label(): string;

    /**
     * The cache key used to store the live Lightspeed count.
     */
    abstract protected function cacheKey(): string;

    /**
     * The number of records currently stored in the local database.
     */
    abstract protected function databaseCount(): int;

    /**
     * The live count reported by the Lightspeed API.
     */
    abstract protected function lightspeedCount(WebshopappApiClient $api): int;

    /**
     * Forget the cached live Lightspeed count so it is fetched fresh on the next render.
     */
    public static function forgetCachedCount(): void
    {
        Cache::forget(app(static::class)->cacheKey());
    }

    protected function getStats(): array
    {
        return [
            $this->buildStat(),
        ];
    }

    private function buildStat(): Stat
    {
        $databaseCount = $this->databaseCount();

        try {
            $lightspeedCount = Cache::remember(
                $this->cacheKey(),
                self::CACHE_TTL,
                fn (): int => (int) $this->lightspeedCount(app(WebshopappApiClient::class)),
            );
        } catch (Throwable $exception) {
            report($exception);

            return Stat::make($this->label(), $databaseCount)
                ->description('Could not reach Lightspeed')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }

        $isInSync = $lightspeedCount === $databaseCount;

        return Stat::make($this->label(), $lightspeedCount)
            ->description($isInSync
                ? "In sync — {$databaseCount} in database"
                : "Out of sync — {$databaseCount} in database")
            ->descriptionIcon($isInSync ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
            ->color($isInSync ? 'success' : 'warning');
    }
}
