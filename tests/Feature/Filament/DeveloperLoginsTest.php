<?php

use DutchCodingCompany\FilamentDeveloperLogins\FilamentDeveloperLoginsPlugin;
use Filament\Facades\Filament;

it('registers developer logins with a single account', function () {
    $plugin = Filament::getPanel('admin')->getPlugin('filament-developer-logins');

    expect($plugin)->toBeInstanceOf(FilamentDeveloperLoginsPlugin::class)
        ->and($plugin->getUsers())->toBe([
            'Admin' => 'admin@admin.com',
        ]);
});

it('only enables developer logins in the local environment', function () {
    // The test suite runs with APP_ENV=testing, so the plugin must be disabled.
    $plugin = Filament::getPanel('admin')->getPlugin('filament-developer-logins');

    expect($plugin->getEnabled())->toBeFalse();
});
