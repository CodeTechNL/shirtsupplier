<?php

namespace App\Filament\Resources\Webhooks;

use App\Filament\Resources\Webhooks\Pages\ListWebhooks;
use App\Filament\Resources\Webhooks\Tables\WebhooksTable;
use App\Models\Webhook;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $recordTitleAttribute = 'address';

    public static function table(Table $table): Table
    {
        return WebhooksTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWebhooks::route('/'),
        ];
    }
}
