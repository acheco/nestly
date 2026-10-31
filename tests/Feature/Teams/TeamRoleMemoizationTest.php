<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;

test('teamRole queries the database when memberships are not loaded', function () {
    $team = Team::factory()->create();
    $user = teamUser($team, TeamRole::Admin);

    $role = null;
    $queries = countQueries(function () use ($user, $team, &$role) {
        $role = $user->teamRole($team);
    });

    expect($role)->toBe(TeamRole::Admin)
        ->and($queries)->toBe(1);
});

test('teamRole reuses loaded memberships without querying', function () {
    $team = Team::factory()->create();
    $user = teamUser($team, TeamRole::Member);
    $user->load('teamMemberships');

    $role = null;
    $queries = countQueries(function () use ($user, $team, &$role) {
        $role = $user->teamRole($team);
    });

    expect($role)->toBe(TeamRole::Member)
        ->and($queries)->toBe(0);
});

test('teamRole returns null for a foreign team from loaded memberships without querying', function () {
    $team = Team::factory()->create();
    $otherTeam = Team::factory()->create();
    $user = teamUser($team, TeamRole::Owner);
    $user->load('teamMemberships');

    $role = 'not-called';
    $queries = countQueries(function () use ($user, $otherTeam, &$role) {
        $role = $user->teamRole($otherTeam);
    });

    expect($role)->toBeNull()
        ->and($queries)->toBe(0);
});

test('team accounts are hydrated with their team without extra queries', function () {
    $team = Team::factory()->create();
    Account::factory()->count(3)->for($team)->create();

    $team = Team::find($team->id);
    $team->load('accounts');

    $queries = countQueries(function () use ($team) {
        $team->accounts->each(fn (Account $account) => $account->team->id);
    });

    expect($queries)->toBe(0)
        ->and($team->accounts->every(fn (Account $account) => $account->team === $team))->toBeTrue();
});
