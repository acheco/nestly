<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

// Spec 001, R11: "/" is not a page. A guest goes to the login, and a signed-in user to the
// dashboard of their current team, or of their personal team when they have no current one.

it('redirects a guest to the login', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

it('redirects a signed-in user to the dashboard of their current team', function () {
    $user = User::factory()->create();
    $shared = Team::factory()->create();
    $shared->members()->attach($user, ['role' => TeamRole::Member->value]);
    $user->switchTeam($shared);

    // A shared team, not the personal one, so always picking the personal team fails here.
    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard', $shared->slug));
});

it('redirects a user without a current team to the dashboard of their personal team', function () {
    $user = User::factory()->create();
    $personal = $user->personalTeam();
    $user->forceFill(['current_team_id' => null])->save();

    // fresh(): the factory's switchTeam() left currentTeam loaded on this instance, and that
    // relation would hide the null current_team_id from homeTeam().
    $user = $user->fresh();

    expect($personal)->not->toBeNull()
        ->and($user->currentTeam)->toBeNull();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertRedirect(route('dashboard', $personal->slug));
});
