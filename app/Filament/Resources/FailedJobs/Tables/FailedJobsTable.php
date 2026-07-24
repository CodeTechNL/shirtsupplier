<?php

namespace App\Filament\Resources\FailedJobs\Tables;

use App\Models\FailedJob;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;

class FailedJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('queue')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('connection')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('exception')
                    ->label('Exception')
                    ->formatStateUsing(fn (string $state): string => (string) str($state)->before("\n")->limit(80))
                    ->tooltip(fn (FailedJob $record): string => (string) str($record->exception)->limit(300))
                    ->wrap(),
                TextColumn::make('failed_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('failed_at', 'desc')
            ->recordActions([
                ViewAction::make(),
                self::retryAction(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::retryBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                self::retryAllAction(),
                self::flushAction(),
            ])
            ->emptyStateHeading('No failed jobs')
            ->emptyStateDescription('Jobs that exhaust their retries will appear here.')
            ->emptyStateIcon(Heroicon::OutlinedCheckCircle);
    }

    protected static function retryAction(): Action
    {
        return Action::make('retry')
            ->label('Retry')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Retry failed job')
            ->modalDescription('This will push the job back onto its original queue to be attempted again.')
            ->action(function (FailedJob $record): void {
                Artisan::call('queue:retry', ['id' => [$record->uuid]]);

                Notification::make()
                    ->title('Job pushed back onto the queue.')
                    ->success()
                    ->send();
            });
    }

    protected static function retryBulkAction(): BulkAction
    {
        return BulkAction::make('retry')
            ->label('Retry selected')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (Collection $records): void {
                Artisan::call('queue:retry', ['id' => $records->pluck('uuid')->all()]);

                Notification::make()
                    ->title($records->count().' job(s) pushed back onto the queue.')
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    protected static function retryAllAction(): Action
    {
        return Action::make('retryAll')
            ->label('Retry all')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Retry all failed jobs')
            ->action(function (): void {
                Artisan::call('queue:retry', ['id' => ['all']]);

                Notification::make()
                    ->title('All failed jobs pushed back onto the queue.')
                    ->success()
                    ->send();
            });
    }

    protected static function flushAction(): Action
    {
        return Action::make('flush')
            ->label('Flush all')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete all failed jobs')
            ->modalDescription('This permanently deletes every failed job record. This cannot be undone.')
            ->action(function (): void {
                Artisan::call('queue:flush');

                Notification::make()
                    ->title('All failed jobs deleted.')
                    ->success()
                    ->send();
            });
    }
}
