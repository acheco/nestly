<?php

use App\Enums\TeamRole;
use App\Models\Budget;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->budget = newBudget($this->team, teamUser($this->team, TeamRole::Owner));
    $this->outsider = User::factory()->create();
});

dataset('budget management abilities', ['update', 'delete']);

test('every member views the team budgets', function (TeamRole $role) {
    $user = teamUser($this->team, $role);

    expect($user->can('viewAny', [Budget::class, $this->team]))->toBeTrue()
        ->and($user->can('view', $this->budget))->toBeTrue();
})->with('roles');

test('create a budget', function (TeamRole $role, bool $allowed) {
    $user = teamUser($this->team, $role);

    expect($user->can('create', [Budget::class, $this->team]))->toBe($allowed);
})->with('only owner and admin');

test('manage a budget', function (string $ability, TeamRole $role, bool $allowed) {
    $user = teamUser($this->team, $role);

    expect($user->can($ability, $this->budget))->toBe($allowed);
})->with('budget management abilities')->with('only owner and admin');

test('the member who created a budget gains nothing from authorship', function (string $ability) {
    // Budgets belong to the team (PRD §1.6.2): created_by records authorship, not ownership.
    $member = teamUser($this->team, TeamRole::Member);
    $budget = newBudget($this->team, $member);

    expect($member->can($ability, $budget))->toBeFalse();
})->with('budget management abilities');

test('an admin of another team cannot manage this team\'s budget', function (string $ability) {
    $otherTeam = Team::factory()->create();
    $foreignAdmin = teamUser($otherTeam, TeamRole::Admin);

    expect($foreignAdmin->can($ability, $this->budget))->toBeFalse()
        ->and($foreignAdmin->can('create', [Budget::class, $this->team]))->toBeFalse();
})->with('budget management abilities');

describe('a non-member', function () {
    test('cannot view the team budgets', function () {
        expect($this->outsider->can('viewAny', [Budget::class, $this->team]))->toBeFalse()
            ->and($this->outsider->can('view', $this->budget))->toBeFalse();
    });

    test('cannot create a budget', function () {
        expect($this->outsider->can('create', [Budget::class, $this->team]))->toBeFalse();
    });

    test('cannot manage a budget', function (string $ability) {
        expect($this->outsider->can($ability, $this->budget))->toBeFalse();
    })->with('budget management abilities');
});
