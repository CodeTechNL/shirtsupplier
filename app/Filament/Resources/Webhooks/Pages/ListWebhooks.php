<?php

namespace App\Filament\Resources\Webhooks\Pages;

use App\Filament\Resources\Webhooks\WebhookResource;
use App\Jobs\Webhooks\InstallProductWebhooks;
use App\Jobs\Webhooks\InstallVariantWebhooks;
use App\Jobs\Webhooks\SyncWebhooks;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListWebhooks extends ListRecords
{
    protected static string $resource = WebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncWebhooks')
                ->label('Sync from Lightspeed')
                ->icon(Heroicon::OutlinedArrowPath)
                ->requiresConfirmation()
                ->modalHeading('Sync webhooks')
                ->modalDescription('This will refresh the list to match the webhooks currently installed at Lightspeed.')
                ->action(function (): void {
                    SyncWebhooks::dispatch();

                    Notification::make()
                        ->title('Webhook sync job dispatched.')
                        ->success()
                        ->send();
                }),
            Action::make('installProductWebhooks')
                ->label('Install product webhooks')
                ->icon(Heroicon::OutlinedSignal)
                ->requiresConfirmation()
                ->modalHeading('Install product webhooks')
                ->modalDescription('This will remove all existing product webhooks and install fresh ones at Lightspeed. Are you sure?')
                ->action(function (): void {
                    InstallProductWebhooks::dispatch();

                    Notification::make()
                        ->title('Product webhook installation job dispatched.')
                        ->success()
                        ->send();
                }),
            Action::make('installVariantWebhooks')
                ->label('Install variant webhooks')
                ->icon(Heroicon::OutlinedSignal)
                ->requiresConfirmation()
                ->modalHeading('Install variant webhooks')
                ->modalDescription('This will remove all existing variant webhooks and install fresh ones at Lightspeed. Are you sure?')
                ->action(function (): void {
                    InstallVariantWebhooks::dispatch();

                    Notification::make()
                        ->title('Variant webhook installation job dispatched.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
