<?php

use App\Models\User;
use Boquizo\FilamentLogViewer\Pages\ListLogs;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->superAdmin()->create());
});

it('is not accessible to non super admins', function () {
    actingAs(User::factory()->create());

    expect(ListLogs::canAccess())->toBeFalse();
});

it('is accessible to super admins', function () {
    expect(ListLogs::canAccess())->toBeTrue();
});

it('forbids non super admins from visiting the page directly', function () {
    actingAs(User::factory()->create());

    get(ListLogs::getUrl())->assertForbidden();
});

it('is placed in the Developers navigation group', function () {
    expect(ListLogs::getNavigationGroup())->toBe('Developers');
});

it('lists the application log file', function () {
    $directory = storage_path('framework/testing/logs');
    File::ensureDirectoryExists($directory);
    File::put(
        $directory.'/laravel.log',
        '[2026-07-25 10:00:00] testing.ERROR: Something went wrong'.PHP_EOL,
    );

    config()->set('filament-log-viewer.storage_path', $directory);

    Livewire::test(ListLogs::class)
        ->assertOk()
        ->assertCountTableRecords(1);

    File::deleteDirectory($directory);
});
