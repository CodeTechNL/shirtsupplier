<?php

namespace App\Filament\Resources\Webhooks\Tables;

use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WebhooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
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
                TextColumn::make('lightspeed_id')
                    ->label('Lightspeed ID')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('item_group')
            ->emptyStateHeading('No webhooks installed')
            ->emptyStateDescription('Install product or variant webhooks, then sync to see them here.')
            ->emptyStateIcon(Heroicon::OutlinedSignalSlash);
    }
}
