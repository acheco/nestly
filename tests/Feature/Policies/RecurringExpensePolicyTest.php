<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\RecurringExpense;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();

    $this->account = Account::factory()->create(['team_id' => $this->team->id]);

    $this->recurring = newRecurringExpense($this->team, $this->account, teamUser($this->team, TeamRole::Owner));

    $this->outsider = User::factory()->create();
});

dataset('roles', [
    'owner' => [TeamRole::Owner],
    'admin' => [TeamRole::Admin],
    'member' => [TeamRole::Member],
    'viewer' => [TeamRole::Viewer],
]);

dataset('management abilities', ['update', 'delete', 'pause', 'resume']);

test('view any recurring expense of the team', function (TeamRole $role) {
    $user = teamUser($this->team, $role);

    expect($user->can('viewAny', [RecurringExpense::class, $this->team]))->toBeTrue();
})->with('roles');

test('view a recurring expense', function (TeamRole $role) {
    $user = teamUser($this->team, $role);

    expect($user->can('view', $this->recurring))->toBeTrue();
})->with('roles');

test('create a recurring expense', function (TeamRole $role, bool $allowed) {
    $user = teamUser($this->team, $role);

    expect($user->can('create', [RecurringExpense::class, $this->team]))->toBe($allowed);
})->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
    'viewer' => [TeamRole::Viewer, false],
]);

test('manage a recurring expense', function (string $ability, TeamRole $role, bool $allowed) {
    $user = teamUser($this->team, $role);

    expect($user->can($ability, $this->recurring))->toBe($allowed);
})->with('management abilities')->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
    'viewer' => [TeamRole::Viewer, false],
]);

describe('a non-member', function () {
    test('cannot view any recurring expense of the team', function () {
        expect($this->outsider->can('viewAny', [RecurringExpense::class, $this->team]))->toBeFalse();
    });

    test('cannot view a recurring expense', function () {
        expect($this->outsider->can('view', $this->recurring))->toBeFalse();
    });

    test('cannot create a recurring expense', function () {
        expect($this->outsider->can('create', [RecurringExpense::class, $this->team]))->toBeFalse();
    });

    test('cannot manage a recurring expense', function (string $ability) {
        expect($this->outsider->can($ability, $this->recurring))->toBeFalse();
    })->with('management abilities');
});

test('an admin demoted to member can no longer update the recurring expense they created', function () {
    $admin = teamUser($this->team, TeamRole::Admin);
    $recurring = newRecurringExpense($this->team, $this->account, $admin);

    expect($admin->can('update', $recurring))->toBeTrue();

    $this->team->members()->updateExistingPivot($admin->id, ['role' => TeamRole::Member->value]);

    expect($admin->can('update', $recurring))->toBeFalse();
});
