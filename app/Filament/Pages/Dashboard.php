<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ProductSyncOverview;
use App\Filament\Widgets\VariantSyncOverview;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncAlgolia')
                ->label('Sync search engine')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync search index')
                ->modalDescription('Re-import all products into the search engine.')
                ->action(function (): void {
                    Product::makeAllSearchable();

                    Notification::make()
                        ->title('Products synced to the search engine')
                        ->success()
                        ->send();
                }),
            Action::make('clearMetrics')
                ->label('Clear metrics')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Clear metrics')
                ->modalDescription('This will clear the cached product and variant counts so they are fetched fresh from Lightspeed on the next load.')
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false)
                ->action(function (): void {
                    ProductSyncOverview::forgetCachedCount();
                    VariantSyncOverview::forgetCachedCount();

                    Notification::make()
                        ->title('Product and variant metrics cleared.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
