<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;

test('team member roles can be updated by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertRedirect(route('teams.edit', $team));

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Admin->value);
});

test('an admin cannot promote a member to admin', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($admin, ['role' => TeamRole::Admin->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($admin)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

    $response->assertForbidden();
});

test('updating the role of a user who is not a member of the team returns a 404', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $otherTeam = Team::factory()->create();
    $foreignUser = teamUser($otherTeam, TeamRole::Member);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $foreignUser]), [
            'role' => TeamRole::Admin->value,
        ]);

    expect($foreignUser->fresh()->teamRole($otherTeam))->toBe(TeamRole::Member)
        ->and($foreignUser->fresh()->belongsToTeam($team))->toBeFalse();

    $response->assertNotFound();
});

test('a member without permission also gets a 404 updating a user outside the team', function () {
    $team = Team::factory()->create();
    $admin = teamUser($team, TeamRole::Admin);
    $otherTeam = Team::factory()->create();
    $foreignUser = teamUser($otherTeam, TeamRole::Member);

    // D-073: the team filter runs before the role check, so a 403 here would be the wrong answer.
    $this->actingAs($admin)
        ->patch(route('teams.members.update', [$team, $foreignUser]), [
            'role' => TeamRole::Admin->value,
        ])
        ->assertNotFound();

    expect($foreignUser->fresh()->teamRole($otherTeam))->toBe(TeamRole::Member);
});

test('team members can be removed by owners', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    $response->assertRedirect(route('teams.edit', $team));

    expect($member->fresh()->belongsToTeam($team))->toBeFalse();
});

test('team members cannot be removed by members', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $viewer = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);

    // D-077 lets admins remove members and viewers; a member still can't remove anyone.
    $response = $this
        ->actingAs($member)
        ->delete(route('teams.members.destroy', [$team, $viewer]));

    $response->assertForbidden();

    expect($viewer->fresh()->belongsToTeam($team))->toBeTrue();
});

test('removing a user who is not a member of the team returns a 404', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $otherTeam = Team::factory()->create();
    $foreignUser = teamUser($otherTeam, TeamRole::Member);
    $foreignUser->update(['current_team_id' => $otherTeam->id]);
    $foreignPrivate = Account::factory()->private()->create([
        'team_id' => $otherTeam->id,
        'owner_id' => $foreignUser->id,
    ]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $foreignUser]));

    expect($foreignUser->fresh()->belongsToTeam($otherTeam))->toBeTrue()
        ->and($foreignUser->fresh()->current_team_id)->toBe($otherTeam->id)
        ->and($foreignPrivate->fresh()->archived)->toBeFalse();

    $response->assertNotFound();
});

test('a member without permission also gets a 404 for a user outside the team', function () {
    $team = Team::factory()->create();
    $admin = teamUser($team, TeamRole::Admin);
    $foreignUser = teamUser(Team::factory()->create(), TeamRole::Member);

    // D-073: the team filter runs before the role check, so a 403 here would be the wrong answer.
    $this->actingAs($admin)
        ->delete(route('teams.members.destroy', [$team, $foreignUser]))
        ->assertNotFound();
});

test('team owner cannot be removed', function () {
    $owner = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $response = $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $owner]));

    $response->assertForbidden();

    expect($owner->fresh()->belongsToTeam($team))->toBeTrue();
});

test('team member role cannot be set to owner', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $response = $this
        ->actingAs($owner)
        ->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Owner->value,
        ]);

    $response->assertSessionHasErrors('role');

    expect($team->members()->where('user_id', $member->id)->first()->pivot->role->value)->toEqual(TeamRole::Member->value);
});

test('removed member current team is set to personal team', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $personalTeam = $member->personalTeam();
    $team = Team::factory()->create();

    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);

    $member->update(['current_team_id' => $team->id]);

    $this
        ->actingAs($owner)
        ->delete(route('teams.members.destroy', [$team, $member]));

    expect($member->fresh()->current_team_id)->toEqual($personalTeam->id);
});

test('nobody can change the role of the team owner', function (TeamRole $role) {
    $owner = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

    $actor = $owner;

    if ($role !== TeamRole::Owner) {
        $actor = User::factory()->create();
        $team->members()->attach($actor, ['role' => $role->value]);
    }

    // D-077 / D-089: an owner demoting themselves would leave the team with no owner.
    $response = $this
        ->actingAs($actor)
        ->patch(route('teams.members.update', [$team, $owner]), [
            'role' => TeamRole::Viewer->value,
        ]);

    $response->assertForbidden();

    expect($team->members()->where('user_id', $owner->id)->first()->pivot->role)->toBe(TeamRole::Owner)
        ->and($team->fresh()->owner()?->is($owner))->toBeTrue();
})->with([
    'owner on themselves' => [TeamRole::Owner],
    'admin' => [TeamRole::Admin],
]);
