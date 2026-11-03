<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/*
 * D-073: someone who is not a member of the team in the URL gets a 403 from
 * EnsureTeamMembership. The actor owns another team, so a role check that
 * looked at the wrong team would let them through.
 */

beforeEach(function () {
    Notification::fake();

    $this->actor = User::factory()->create();
    Team::factory()->create()->members()->attach($this->actor, ['role' => TeamRole::Owner->value]);

    $this->team = Team::factory()->create(['name' => 'Foreign team']);
    $this->teamOwner = teamUser($this->team, TeamRole::Owner);
    $this->teamMember = teamUser($this->team, TeamRole::Member);
    $this->invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'invited_by' => $this->teamOwner->id,
    ]);
});

test('the owner of another team cannot write to a team they do not belong to', function (string $action) {
    $currentTeamId = $this->actor->fresh()->current_team_id;

    $this->actingAs($this->actor);

    $response = match ($action) {
        'update the team' => $this->patch(route('teams.update', $this->team), ['name' => 'Hijacked']),
        'delete the team' => $this->delete(route('teams.destroy', $this->team), ['name' => $this->team->name]),
        'switch to the team' => $this->post(route('teams.switch', $this->team)),
        'leave the team' => $this->delete(route('teams.leave', $this->team)),
        'update a member' => $this->patch(route('teams.members.update', [$this->team, $this->teamMember]), ['role' => TeamRole::Admin->value]),
        'remove a member' => $this->delete(route('teams.members.destroy', [$this->team, $this->teamMember])),
        'create an invitation' => $this->post(route('teams.invitations.store', $this->team), ['email' => 'someone@example.com', 'role' => TeamRole::Member->value]),
        'cancel an invitation' => $this->delete(route('teams.invitations.destroy', [$this->team, $this->invitation])),
    };

    expect(Team::find($this->team->id)?->name)->toBe('Foreign team')
        ->and($this->teamMember->fresh()->teamRole($this->team))->toBe(TeamRole::Member)
        ->and($this->teamOwner->fresh()->teamRole($this->team))->toBe(TeamRole::Owner)
        ->and($this->actor->fresh()->belongsToTeam($this->team))->toBeFalse()
        ->and($this->actor->fresh()->current_team_id)->toBe($currentTeamId)
        ->and($this->team->invitations()->count())->toBe(1);

    $this->assertModelExists($this->invitation);
    Notification::assertNothingSent();

    $response->assertForbidden();
})->with([
    'update the team',
    'delete the team',
    'switch to the team',
    'leave the team',
    'update a member',
    'remove a member',
    'create an invitation',
    'cancel an invitation',
]);
