<?php

namespace App\Providers;

use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;
use WebshopappApiClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(WebshopappApiClient::class, fn () => new WebshopappApiClient(
            config('webshop.lightspeed.cluster'),
            config('webshop.lightspeed.key'),
            config('webshop.lightspeed.secret'),
            config('webshop.lightspeed.language'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Table::configureUsing(function (Table $table): void {
            $table->defaultPaginationPageOption(50);
        });
    }
}
