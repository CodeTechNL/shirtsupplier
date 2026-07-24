<?php

namespace App\Filament\Resources\FailedJobs\Pages;

use App\Filament\Resources\FailedJobs\FailedJobResource;
use App\Filament\Widgets\QueueStatsOverview;
use Filament\Resources\Pages\ListRecords;

class ListFailedJobs extends ListRecords
{
    protected static string $resource = FailedJobResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            QueueStatsOverview::class,
        ];
    }
}
