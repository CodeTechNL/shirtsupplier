<?php

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    actingAs(User::factory()->create());
});

it('lists users', function () {
    $users = User::factory()->count(3)->create();

    Livewire::test(ListUsers::class)
        ->assertOk()
        ->assertCanSeeTableRecords($users);
});

it('creates a user with a hashed password', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Jane Doe',
            'email' => 'jane@example.test',
            'password' => 'secret-password',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'jane@example.test')->firstOrFail();

    expect($user->name)->toBe('Jane Doe')
        ->and($user->super_admin)->toBeFalse()
        ->and(Hash::check('secret-password', $user->password))->toBeTrue();
});

it('lets a super admin create another super admin', function () {
    actingAs(User::factory()->superAdmin()->create());

    Livewire::test(CreateUser::class)
        ->assertFormFieldIsVisible('super_admin')
        ->fillForm([
            'name' => 'Root',
            'email' => 'root@example.test',
            'password' => 'secret-password',
            'super_admin' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas('users', ['email' => 'root@example.test', 'super_admin' => true]);
});

it('hides the super admin toggle from normal users', function () {
    Livewire::test(CreateUser::class)
        ->assertFormFieldIsHidden('super_admin');
});

it('prevents a normal user from forcing super admin on create', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'Sneaky',
            'email' => 'sneaky@example.test',
            'password' => 'secret-password',
        ])
        ->set('data.super_admin', true)
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'sneaky@example.test')->firstOrFail();

    expect($user->super_admin)->toBeFalse();
});

it('prevents a normal user from promoting a user to super admin on edit', function () {
    $target = User::factory()->create(['super_admin' => false]);

    Livewire::test(EditUser::class, ['record' => $target->getKey()])
        ->set('data.super_admin', true)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($target->refresh()->super_admin)->toBeFalse();
});

it('validates required fields when creating a user', function () {
    Livewire::test(CreateUser::class)
        ->fillForm(['name' => '', 'email' => '', 'password' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'required', 'password' => 'required']);
});

it('keeps the existing password when editing without a new one', function () {
    $user = User::factory()->create(['password' => Hash::make('original-password')]);

    Livewire::test(EditUser::class, ['record' => $user->getKey()])
        ->fillForm(['name' => 'Renamed', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->name)->toBe('Renamed')
        ->and(Hash::check('original-password', $user->password))->toBeTrue();
});
