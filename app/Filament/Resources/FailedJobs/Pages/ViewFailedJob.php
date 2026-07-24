<?php

namespace App\Filament\Resources\FailedJobs\Pages;

use App\Filament\Resources\FailedJobs\FailedJobResource;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewFailedJob extends ViewRecord
{
    protected static string $resource = FailedJobResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('uuid')
                    ->label('UUID')
                    ->copyable(),
                TextEntry::make('connection'),
                TextEntry::make('queue')
                    ->badge(),
                TextEntry::make('failed_at')
                    ->dateTime(),
                TextEntry::make('exception')
                    ->label('Exception')
                    ->columnSpanFull()
                    ->copyable(),
                TextEntry::make('payload')
                    ->label('Payload')
                    ->columnSpanFull()
                    ->copyable(),
            ]);
    }
}
