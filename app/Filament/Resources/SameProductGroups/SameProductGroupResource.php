<?php

namespace App\Filament\Resources\SameProductGroups;

use App\Filament\Resources\SameProductGroups\Pages\EditSameProductGroup;
use App\Filament\Resources\SameProductGroups\Pages\ListSameProductGroups;
use App\Filament\Resources\SameProductGroups\Schemas\SameProductGroupForm;
use App\Filament\Resources\SameProductGroups\Tables\SameProductGroupsTable;
use App\Models\SameProductGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SameProductGroupResource extends Resource
{
    protected static ?string $model = SameProductGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return SameProductGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SameProductGroupsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSameProductGroups::route('/'),
            'edit' => EditSameProductGroup::route('/{record}/edit'),
        ];
    }
}
