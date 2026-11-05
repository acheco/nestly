<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

dataset('valid team icons', ['home', 'user-round-group', 'trending-up', 'clock-3']);

dataset('invalid team icons', [
    'hex color' => '#4F7CFF',
    'uppercase' => 'Home',
    'spaces' => 'user round',
    'underscore' => 'user_round',
    'leading hyphen' => '-home',
    'trailing hyphen' => 'home-',
    'double hyphen' => 'user--round',
    'markup' => '<script>',
    'too long' => str_repeat('a', 256),
]);

test('creating a team accepts a lucide icon name', function (string $icon) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Familia', 'icon' => $icon])
        ->assertSessionHasNoErrors();

    expect(Team::where('name', 'Familia')->exists())->toBeTrue();
})->with('valid team icons');

test('creating a team rejects an invalid icon', function (string $icon) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Familia', 'icon' => $icon])
        ->assertSessionHasErrors('icon');

    expect(Team::where('name', 'Familia')->exists())->toBeFalse();
})->with('invalid team icons');

test('updating a team accepts a lucide icon name', function (string $icon) {
    $team = Team::factory()->create(['name' => 'Antes']);
    $owner = teamUser($team, TeamRole::Owner);

    $this->actingAs($owner)
        ->patch(route('teams.update', $team), ['name' => 'Después', 'icon' => $icon])
        ->assertSessionHasNoErrors();

    expect($team->fresh()->name)->toBe('Después');
})->with('valid team icons');

test('updating a team rejects an invalid icon', function (string $icon) {
    $team = Team::factory()->create(['name' => 'Antes']);
    $owner = teamUser($team, TeamRole::Owner);

    $this->actingAs($owner)
        ->patch(route('teams.update', $team), ['name' => 'Después', 'icon' => $icon])
        ->assertSessionHasErrors('icon');

    expect($team->fresh()->name)->toBe('Antes');
})->with('invalid team icons');

test('the icon is optional', function (array $payload) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('teams.store'), ['name' => 'Familia', ...$payload])
        ->assertSessionHasNoErrors();

    expect(Team::where('name', 'Familia')->exists())->toBeTrue();
})->with([
    'omitted' => [[]],
    'null' => [['icon' => null]],
]);
