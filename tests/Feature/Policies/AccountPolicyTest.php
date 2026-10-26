<?php

use App\Enums\AccountVisibility;
use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();

    $this->teamAccount = Account::factory()->create(['team_id' => $this->team->id]);

    // Privada de otro miembro: "ajena" para cualquier usuario que se pruebe.
    $this->privateOwner = teamUser($this->team, TeamRole::Member);
    $this->privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->privateOwner->id,
    ]);

    $this->outsider = User::factory()->create();
});

dataset('management abilities', ['update', 'archive', 'delete']);

function newAccount(AccountVisibility $visibility): Account
{
    return new Account(['visibility' => $visibility]);
}

test('view a team account', function (TeamRole $role) {
    $user = teamUser($this->team, $role);

    expect($user->can('view', $this->teamAccount))->toBeTrue();
})->with('roles');

test('view someone else\'s private account', function (TeamRole $role) {
    $user = teamUser($this->team, $role);

    expect($user->can('view', $this->privateAccount))->toBeTrue();
})->with('roles');

test('view the balance of a team account', function (TeamRole $role) {
    $user = teamUser($this->team, $role);

    expect($user->can('viewBalance', $this->teamAccount))->toBeTrue();
})->with('roles');

test('view the balance of someone else\'s private account', function (TeamRole $role) {
    $user = teamUser($this->team, $role);

    expect($user->can('viewBalance', $this->privateAccount))->toBeFalse();
})->with('roles');

test('create a team account', function (TeamRole $role, bool $allowed) {
    $user = teamUser($this->team, $role);

    expect($user->can('create', [Account::class, $this->team, AccountVisibility::Team]))->toBe($allowed);
})->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
    'viewer' => [TeamRole::Viewer, false],
]);

test('create a private account', function (TeamRole $role, bool $allowed) {
    $user = teamUser($this->team, $role);

    expect($user->can('create', [Account::class, $this->team, AccountVisibility::Private]))->toBe($allowed);
})->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, true],
    'viewer' => [TeamRole::Viewer, false],
]);

test('manage a team account', function (string $ability, TeamRole $role, bool $allowed) {
    $user = teamUser($this->team, $role);

    expect($user->can($ability, $this->teamAccount))->toBe($allowed);
})->with('management abilities')->with([
    'owner' => [TeamRole::Owner, true],
    'admin' => [TeamRole::Admin, true],
    'member' => [TeamRole::Member, false],
    'viewer' => [TeamRole::Viewer, false],
]);

test('manage someone else\'s private account', function (string $ability, TeamRole $role) {
    $user = teamUser($this->team, $role);

    expect($user->can($ability, $this->privateAccount))->toBeFalse();
})->with('management abilities')->with('roles');

describe('a non-member', function () {
    test('cannot view a team account', function () {
        expect($this->outsider->can('view', $this->teamAccount))->toBeFalse();
    });

    test('cannot view a private account', function () {
        expect($this->outsider->can('view', $this->privateAccount))->toBeFalse();
    });

    test('cannot view the balance of a team account', function () {
        expect($this->outsider->can('viewBalance', $this->teamAccount))->toBeFalse();
    });

    test('cannot view the balance of a private account', function () {
        expect($this->outsider->can('viewBalance', $this->privateAccount))->toBeFalse();
    });

    test('cannot create an account', function (AccountVisibility $visibility) {
        expect($this->outsider->can('create', $visibility))->toBeFalse();
    })->with([
        'team' => [AccountVisibility::Team],
        'private' => [AccountVisibility::Private],
    ]);

    test('cannot manage a team account', function (string $ability) {
        expect($this->outsider->can($ability, $this->teamAccount))->toBeFalse();
    })->with('management abilities');

    test('cannot manage a private account', function (string $ability) {
        expect($this->outsider->can($ability, $this->privateAccount))->toBeFalse();
    })->with('management abilities');
});

test('the owner of a private account sees its balance and manages it', function () {
    expect($this->privateOwner->can('viewBalance', $this->privateAccount))->toBeTrue()
        ->and($this->privateOwner->can('update', $this->privateAccount))->toBeTrue()
        ->and($this->privateOwner->can('archive', $this->privateAccount))->toBeTrue()
        ->and($this->privateOwner->can('delete', $this->privateAccount))->toBeTrue();
});

test('an owner who left the team can no longer manage their private account', function (string $ability) {
    $this->team->members()->detach($this->privateOwner);

    expect($this->privateOwner->can($ability, $this->privateAccount))->toBeFalse();
})->with('management abilities');

test('a viewer who owns a private account cannot manage it', function (string $ability) {
    // Datos inconsistentes forzados: un viewer no puede crear privadas, pero si llega a tener una
    // (p. ej. lo degradaron), gestionarla exige ser al menos member.
    $viewer = teamUser($this->team, TeamRole::Viewer);
    $account = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $viewer->id,
    ]);

    expect($viewer->can($ability, $account))->toBeFalse();
})->with('management abilities');
