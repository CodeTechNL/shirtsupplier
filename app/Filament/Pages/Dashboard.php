<?php

namespace App\Filament\Pages;

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
                ->label('Sync to Algolia')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->modalHeading('Sync search index')
                ->modalDescription('Re-import all products into the Algolia search index.')
                ->action(function (): void {
                    Product::makeAllSearchable();

                    Notification::make()
                        ->title('Products synced to Algolia')
                        ->success()
                        ->send();
                }),
        ];
    }
}
