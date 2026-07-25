<?php

namespace App\Filament\Resources\Variants\Pages;

use App\Filament\Resources\Variants\VariantResource;
use App\Filament\Widgets\VariantSyncOverview;
use App\Jobs\SyncVariants;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListVariants extends ListRecords
{
    protected static string $resource = VariantResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            VariantSyncOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncVariants')
                ->label('Sync Variants')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalHeading('Sync Variants')
                ->modalDescription('This will sync all variants from Lightspeed. New variants will be created, existing variants updated, and removed variants deleted.')
                ->action(function () {
                    SyncVariants::dispatch();

                    Notification::make()
                        ->title('Variant sync job dispatched.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
