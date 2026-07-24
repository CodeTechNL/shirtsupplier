<?php

namespace App\Filament\Resources\Variants\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VariantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('product.fulltitle')
                    ->label('Product')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? ($state['nl'] ?? '') : (string) $state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ean')
                    ->label('EAN')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('price_incl')
                    ->label('Price (incl.)')
                    ->money('EUR')
                    ->sortable(),
                TextColumn::make('stock_level')
                    ->label('Stock')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
