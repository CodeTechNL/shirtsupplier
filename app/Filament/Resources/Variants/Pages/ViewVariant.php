<?php

namespace App\Filament\Resources\Variants\Pages;

use App\Filament\Resources\Variants\VariantResource;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewVariant extends ViewRecord
{
    protected static string $resource = VariantResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title')
                    ->label('Title'),
                TextEntry::make('product.fulltitle')
                    ->label('Product')
                    ->formatStateUsing(fn (mixed $state): string => is_array($state) ? ($state['nl'] ?? '') : (string) $state),
                TextEntry::make('sku')
                    ->label('SKU'),
                TextEntry::make('ean')
                    ->label('EAN'),
                TextEntry::make('article_code')
                    ->label('Article Code'),
                TextEntry::make('price_excl')
                    ->label('Price (excl.)')
                    ->money('EUR'),
                TextEntry::make('price_incl')
                    ->label('Price (incl.)')
                    ->money('EUR'),
                TextEntry::make('stock_tracking')
                    ->label('Stock Tracking'),
                TextEntry::make('stock_level')
                    ->label('Stock Level')
                    ->numeric(),
                TextEntry::make('weight')
                    ->label('Weight (g)')
                    ->numeric(),
                TextEntry::make('sort_order')
                    ->label('Sort Order')
                    ->numeric(),
                IconEntry::make('is_default')
                    ->label('Default')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->label('Created At')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->label('Updated At')
                    ->dateTime(),
            ]);
    }
}
