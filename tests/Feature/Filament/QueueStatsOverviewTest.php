<?php

use App\Filament\Widgets\QueueStatsOverview;
use App\Models\FailedJob;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(LazilyRefreshDatabase::class);

function queueStatValues(): array
{
    $widget = new QueueStatsOverview;
    $method = new ReflectionMethod($widget, 'getStats');
    $method->setAccessible(true);

    return collect($method->invoke($widget))
        ->map(fn ($stat) => $stat->getValue())
        ->all();
}

function pushJob(?int $reservedAt = null): void
{
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => $reservedAt,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);
}

it('counts jobs in the database queue and failed jobs', function () {
    pushJob();
    pushJob();
    pushJob(reservedAt: now()->timestamp);

    FailedJob::create([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'boom',
        'failed_at' => now(),
    ]);

    // [jobs in queue, failed jobs]
    expect(queueStatValues())->toBe([3, 1]);
});

it('renders for an authenticated user', function () {
    actingAs(User::factory()->create());

    Livewire::test(QueueStatsOverview::class)
        ->assertOk()
        ->assertSee('Jobs in queue')
        ->assertSee('Failed jobs');
});
