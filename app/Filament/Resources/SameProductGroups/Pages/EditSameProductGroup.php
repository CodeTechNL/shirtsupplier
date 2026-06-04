<?php

namespace App\Filament\Resources\SameProductGroups\Pages;

use App\Filament\Resources\SameProductGroups\SameProductGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSameProductGroup extends EditRecord
{
    protected static string $resource = SameProductGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
