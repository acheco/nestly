<?php

use App\Enums\CategoryKind;
use App\Enums\TeamRole;
use App\Models\Category;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();

    // TeamObserver seeds the team's own system categories when the team is created.
    $this->systemCategory = $this->team->categories()->where('is_system', true)->firstOrFail();
    $this->category = makeCategory($this->team, CategoryKind::Expense);

    $this->outsider = User::factory()->create();
});

dataset('category management abilities', ['update', 'archive', 'delete']);

test('every member views the team categories, system ones included', function (TeamRole $role) {
    $user = teamUser($this->team, $role);

    expect($user->can('viewAny', [Category::class, $this->team]))->toBeTrue()
        ->and($user->can('view', $this->category))->toBeTrue()
        ->and($user->can('view', $this->systemCategory))->toBeTrue();
})->with('roles');

test('create a category', function (TeamRole $role, bool $allowed) {
    $user = teamUser($this->team, $role);

    expect($user->can('create', [Category::class, $this->team]))->toBe($allowed);
})->with('only owner and admin');

test('manage a regular category', function (string $ability, TeamRole $role, bool $allowed) {
    $user = teamUser($this->team, $role);

    expect($user->can($ability, $this->category))->toBe($allowed);
})->with('category management abilities')->with('only owner and admin');

test('nobody updates or deletes a system category, not even the owner', function (string $ability, TeamRole $role) {
    // is_system wins over the role (ERD §4.3.19).
    $user = teamUser($this->team, $role);

    expect($user->can($ability, $this->systemCategory))->toBeFalse();
})->with(['update', 'delete'])->with('roles');

test('archive a system category', function (TeamRole $role, bool $allowed) {
    // System categories can be hidden from the picker; archiving never deletes (PRD §1.6.5).
    $user = teamUser($this->team, $role);

    expect($user->can('archive', $this->systemCategory))->toBe($allowed);
})->with('only owner and admin');

test('each team has its own copy of the system categories', function () {
    // Archiving is per team because the rows are per team: another team's admin
    // cannot touch this team's copy, and each team owns a distinct row.
    $otherTeam = Team::factory()->create();
    $foreignAdmin = teamUser($otherTeam, TeamRole::Admin);
    $foreignCopy = $otherTeam->categories()
        ->where('is_system', true)
        ->where('name', $this->systemCategory->name)
        ->firstOrFail();

    expect($foreignCopy->id)->not->toBe($this->systemCategory->id)
        ->and($foreignAdmin->can('archive', $foreignCopy))->toBeTrue()
        ->and($foreignAdmin->can('archive', $this->systemCategory))->toBeFalse();
});

describe('a non-member', function () {
    test('cannot view the team categories', function () {
        expect($this->outsider->can('viewAny', [Category::class, $this->team]))->toBeFalse()
            ->and($this->outsider->can('view', $this->category))->toBeFalse();
    });

    test('cannot create a category', function () {
        expect($this->outsider->can('create', [Category::class, $this->team]))->toBeFalse();
    });

    test('cannot manage a category', function (string $ability) {
        expect($this->outsider->can($ability, $this->category))->toBeFalse();
    })->with('category management abilities');
});
