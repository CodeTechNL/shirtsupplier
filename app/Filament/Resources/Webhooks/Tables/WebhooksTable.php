<?php

namespace App\Filament\Resources\Webhooks\Tables;

use App\Models\Webhook;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Throwable;
use WebshopappApiClient;

class WebhooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lightspeed_id')
                    ->label('Lightspeed ID')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->sortable(),
                TextColumn::make('item_group')
                    ->label('Item group')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('item_action')
                    ->label('Action')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('language')
                    ->badge()
                    ->sortable(),
                TextColumn::make('address')
                    ->searchable()
                    ->limit(60)
                    ->copyable()
                    ->tooltip(fn (string $state): string => $state),
                TextColumn::make('format')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                DeleteAction::make()
                    ->modalDescription('This will delete the webhook at Lightspeed as well. Are you sure?')
                    ->before(function (DeleteAction $action, Webhook $record): void {
                        if ($record->lightspeed_id === null) {
                            return;
                        }

                        try {
                            app(WebshopappApiClient::class)->webhooks->delete($record->lightspeed_id);
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->title('Could not delete the webhook at Lightspeed.')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkAction::make('delete')
                    ->label('Delete selected')
                    ->icon(Heroicon::OutlinedTrash)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete selected webhooks')
                    ->modalDescription('This will delete the selected webhooks at Lightspeed as well. Are you sure?')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): void {
                        $api = app(WebshopappApiClient::class);
                        $failed = 0;

                        foreach ($records as $record) {
                            if ($record->lightspeed_id !== null) {
                                try {
                                    $api->webhooks->delete($record->lightspeed_id);
                                } catch (Throwable $exception) {
                                    report($exception);

                                    $failed++;

                                    continue;
                                }
                            }

                            $record->delete();
                        }

                        $deleted = $records->count() - $failed;

                        if ($failed > 0) {
                            Notification::make()
                                ->title("Deleted {$deleted} of {$records->count()} webhooks.")
                                ->body("{$failed} could not be deleted at Lightspeed and were kept.")
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title("Deleted {$deleted} webhooks.")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('item_group')
            ->emptyStateHeading('No webhooks installed')
            ->emptyStateDescription('Install webhooks at Lightspeed, then sync to see them here.')
            ->emptyStateIcon(Heroicon::OutlinedSignalSlash);
    }
}
