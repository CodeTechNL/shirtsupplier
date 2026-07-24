<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;

uses(LazilyRefreshDatabase::class);

it('forbids a normal user from deleting a super admin at the policy level', function () {
    $normalUser = User::factory()->create();
    $superAdmin = User::factory()->superAdmin()->create();

    expect($normalUser->can('delete', $superAdmin))->toBeFalse();
});

it('allows a super admin to delete another super admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $otherSuperAdmin = User::factory()->superAdmin()->create();

    expect($superAdmin->can('delete', $otherSuperAdmin))->toBeTrue();
});

it('allows a normal user to delete another normal user', function () {
    $normalUser = User::factory()->create();
    $otherUser = User::factory()->create();

    expect($normalUser->can('delete', $otherUser))->toBeTrue();
});

it('hides the delete action for a super admin row from a normal user', function () {
    actingAs(User::factory()->create());

    $superAdmin = User::factory()->superAdmin()->create();
    $normalUser = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('delete')->table($superAdmin))
        ->assertActionVisible(TestAction::make('delete')->table($normalUser));
});

it('lets a normal user delete a normal user through the table action', function () {
    actingAs(User::factory()->create());

    $target = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('delete')->table($target));

    assertModelMissing($target);
});

it('lets a super admin delete a super admin through the table action', function () {
    actingAs(User::factory()->superAdmin()->create());

    $target = User::factory()->superAdmin()->create();

    Livewire::test(ListUsers::class)
        ->callAction(TestAction::make('delete')->table($target));

    assertModelMissing($target);
});

it('skips super admins when a normal user bulk deletes', function () {
    actingAs(User::factory()->create());

    $superAdmin = User::factory()->superAdmin()->create();
    $normalUser = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->selectTableRecords([$superAdmin->getKey(), $normalUser->getKey()])
        ->callAction(TestAction::make('delete')->table()->bulk());

    assertModelExists($superAdmin);
    assertModelMissing($normalUser);
});
