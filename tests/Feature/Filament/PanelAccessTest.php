<?php

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(LazilyRefreshDatabase::class);

it('allows any authenticated user to access the admin panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBeTrue();

    actingAs($user)
        ->get('/admin')
        ->assertOk();
});

it('denies guests access to the admin panel', function () {
    get('/admin')->assertRedirect();
});

it('exposes the profile page so users can change their password', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('filament.admin.auth.profile'))
        ->assertOk();
});
