<?php

namespace App\Filament\Resources\Variants;

use App\Filament\Resources\Variants\Pages\ListVariants;
use App\Filament\Resources\Variants\Pages\ViewVariant;
use App\Filament\Resources\Variants\Tables\VariantsTable;
use App\Models\Variant;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class VariantResource extends Resource
{
    protected static ?string $model = Variant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function table(Table $table): Table
    {
        return VariantsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVariants::route('/'),
            'view' => ViewVariant::route('/{record}'),
        ];
    }
}
