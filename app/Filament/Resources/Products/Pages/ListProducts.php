<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Jobs\SyncProducts;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncProducts')
                ->label('Sync Products')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalHeading('Sync Products')
                ->modalDescription('This will sync all products from Lightspeed. New products will be created, existing products updated, and removed products deleted.')
                ->action(function () {
                    SyncProducts::dispatch();

                    Notification::make()
                        ->title('Product sync job dispatched.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
