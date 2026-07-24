<?php

namespace App\Filament\Widgets;

use App\Models\FailedJob;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class QueueStatsOverview extends BaseWidget
{
    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        $queued = DB::table('jobs')->count();
        $processing = DB::table('jobs')->whereNotNull('reserved_at')->count();
        $failed = FailedJob::count();

        return [
            Stat::make('Jobs in queue', $queued)
                ->description($processing > 0 ? "{$processing} currently processing" : 'Waiting to be processed')
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($queued > 0 ? 'warning' : 'success'),
            Stat::make('Failed jobs', $failed)
                ->description('Exhausted all retry attempts')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($failed > 0 ? 'danger' : 'success'),
        ];
    }
}
