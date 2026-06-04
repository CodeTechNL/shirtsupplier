<?php

namespace App\Filament\Resources\SameProductGroups\Pages;

use App\Filament\Resources\SameProductGroups\SameProductGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSameProductGroups extends ListRecords
{
    protected static string $resource = SameProductGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
