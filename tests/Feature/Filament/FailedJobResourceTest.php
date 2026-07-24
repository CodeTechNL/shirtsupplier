<?php

use App\Filament\Resources\FailedJobs\Pages\ListFailedJobs;
use App\Models\FailedJob;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseMissing;

uses(LazilyRefreshDatabase::class);

function createFailedJob(array $overrides = []): FailedJob
{
    $uuid = $overrides['uuid'] ?? (string) Str::uuid();

    return FailedJob::create(array_merge([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode([
            'uuid' => $uuid,
            'displayName' => 'App\\Jobs\\TestJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries' => null,
            'attempts' => 1,
            'data' => ['commandName' => 'App\\Jobs\\TestJob'],
        ]),
        'exception' => "RuntimeException: boom\n#0 stack trace",
        'failed_at' => now(),
    ], $overrides));
}

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('lists failed jobs', function () {
    $jobs = collect(range(1, 3))->map(fn () => createFailedJob());

    Livewire::test(ListFailedJobs::class)
        ->assertOk()
        ->assertCanSeeTableRecords($jobs)
        ->assertCountTableRecords(3);
});

it('deletes a failed job', function () {
    $job = createFailedJob();

    Livewire::test(ListFailedJobs::class)
        ->callAction(TestAction::make('delete')->table($job))
        ->assertNotified();

    assertDatabaseMissing('failed_jobs', ['uuid' => $job->uuid]);
});

it('retries a failed job back onto the queue', function () {
    $job = createFailedJob();

    expect(DB::table('jobs')->count())->toBe(0);

    Livewire::test(ListFailedJobs::class)
        ->callAction(TestAction::make('retry')->table($job))
        ->assertNotified();

    assertDatabaseMissing('failed_jobs', ['uuid' => $job->uuid]);
    expect(DB::table('jobs')->count())->toBe(1);
});

it('flushes all failed jobs', function () {
    collect(range(1, 3))->each(fn () => createFailedJob());

    Livewire::test(ListFailedJobs::class)
        ->callAction(TestAction::make('flush')->table())
        ->assertNotified();

    expect(FailedJob::count())->toBe(0);
});
