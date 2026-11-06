<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

/*
| D-077: an admin manages members and viewers only. They never act on admins or the
| owner, and never assign the admin role. The owner manages everyone but themselves.
*/

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->owner = teamUser($this->team, TeamRole::Owner);
    $this->admin = teamUser($this->team, TeamRole::Admin);
    $this->otherAdmin = teamUser($this->team, TeamRole::Admin);
    $this->member = teamUser($this->team, TeamRole::Member);
    $this->viewer = teamUser($this->team, TeamRole::Viewer);

    $this->roleOf = fn ($user) => $this->team->memberships()->where('user_id', $user->id)->value('role');
});

// --- Changing roles -------------------------------------------------------------------

test('an admin moves members and viewers between those two roles', function (string $target, TeamRole $newRole) {
    $user = $this->{$target};

    $this->actingAs($this->admin)
        ->patch(route('teams.members.update', [$this->team, $user]), ['role' => $newRole->value])
        ->assertRedirect(route('teams.edit', $this->team));

    expect(($this->roleOf)($user))->toBe($newRole);
})->with([
    'member to viewer' => ['member', TeamRole::Viewer],
    'viewer to member' => ['viewer', TeamRole::Member],
]);

test('an admin cannot change roles outside members and viewers', function (string $target, TeamRole $newRole) {
    $user = $this->{$target};
    $before = ($this->roleOf)($user);

    $this->actingAs($this->admin)
        ->patch(route('teams.members.update', [$this->team, $user]), ['role' => $newRole->value])
        ->assertForbidden();

    expect(($this->roleOf)($user))->toBe($before);
})->with([
    'promote a member to admin' => ['member', TeamRole::Admin],
    'promote a viewer to admin' => ['viewer', TeamRole::Admin],
    'demote another admin' => ['otherAdmin', TeamRole::Member],
    'demote themselves' => ['admin', TeamRole::Viewer],
    'change the owner' => ['owner', TeamRole::Member],
]);

test('the owner changes the role of any other member, admins included', function (string $target, TeamRole $newRole) {
    $user = $this->{$target};

    $this->actingAs($this->owner)
        ->patch(route('teams.members.update', [$this->team, $user]), ['role' => $newRole->value])
        ->assertRedirect(route('teams.edit', $this->team));

    expect(($this->roleOf)($user))->toBe($newRole);
})->with([
    'promote a member to admin' => ['member', TeamRole::Admin],
    'demote an admin to viewer' => ['admin', TeamRole::Viewer],
]);

test('a member cannot change anyone\'s role', function () {
    $this->actingAs($this->member)
        ->patch(route('teams.members.update', [$this->team, $this->viewer]), ['role' => TeamRole::Member->value])
        ->assertForbidden();

    expect(($this->roleOf)($this->viewer))->toBe(TeamRole::Viewer);
});

// --- Removing members -----------------------------------------------------------------

test('an admin removes members and viewers', function (string $target) {
    $user = $this->{$target};

    $this->actingAs($this->admin)
        ->delete(route('teams.members.destroy', [$this->team, $user]))
        ->assertRedirect(route('teams.edit', $this->team));

    expect($user->fresh()->belongsToTeam($this->team))->toBeFalse();
})->with(['member', 'viewer']);

test('an admin cannot remove admins or the owner', function (string $target) {
    $user = $this->{$target};

    $this->actingAs($this->admin)
        ->delete(route('teams.members.destroy', [$this->team, $user]))
        ->assertForbidden();

    expect($user->fresh()->belongsToTeam($this->team))->toBeTrue();
})->with(['otherAdmin', 'owner']);

test('the owner removes admins', function () {
    $this->actingAs($this->owner)
        ->delete(route('teams.members.destroy', [$this->team, $this->admin]))
        ->assertRedirect(route('teams.edit', $this->team));

    expect($this->admin->fresh()->belongsToTeam($this->team))->toBeFalse();
});

test('a member removed by an admin has their private accounts archived (D-016)', function () {
    $private = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->member->id,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('teams.members.destroy', [$this->team, $this->member]))
        ->assertRedirect(route('teams.edit', $this->team));

    expect($private->fresh()->archived)->toBeTrue()
        ->and($private->fresh()->owner_id)->toBe($this->member->id);
});

// --- Invitations ----------------------------------------------------------------------

test('an admin invites members and viewers', function (TeamRole $role) {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('teams.invitations.store', $this->team), ['email' => 'new@example.com', 'role' => $role->value])
        ->assertRedirect(route('teams.edit', $this->team));

    expect($this->team->invitations()->where('email', 'new@example.com')->value('role'))->toBe($role);
    Notification::assertSentOnDemand(TeamInvitationNotification::class);
})->with([TeamRole::Member, TeamRole::Viewer]);

test('an admin cannot invite someone as admin', function () {
    Notification::fake();

    $this->actingAs($this->admin)
        ->post(route('teams.invitations.store', $this->team), ['email' => 'new@example.com', 'role' => TeamRole::Admin->value])
        ->assertForbidden();

    expect($this->team->invitations()->count())->toBe(0);
    Notification::assertNothingSent();
});

test('the owner invites someone as admin', function () {
    Notification::fake();

    $this->actingAs($this->owner)
        ->post(route('teams.invitations.store', $this->team), ['email' => 'new@example.com', 'role' => TeamRole::Admin->value])
        ->assertRedirect(route('teams.edit', $this->team));

    expect($this->team->invitations()->value('role'))->toBe(TeamRole::Admin);
});

test('an admin cancels member and viewer invitations', function (TeamRole $role) {
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'invited_by' => $this->owner->id,
        'role' => $role,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('teams.invitations.destroy', [$this->team, $invitation]))
        ->assertRedirect(route('teams.edit', $this->team));

    expect(TeamInvitation::find($invitation->id))->toBeNull();
})->with([TeamRole::Member, TeamRole::Viewer]);

test('an admin cannot cancel an admin invitation', function () {
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'invited_by' => $this->owner->id,
        'role' => TeamRole::Admin,
    ]);

    $this->actingAs($this->admin)
        ->delete(route('teams.invitations.destroy', [$this->team, $invitation]))
        ->assertForbidden();

    expect(TeamInvitation::find($invitation->id))->not->toBeNull();
});

test('the owner cancels an admin invitation', function () {
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'invited_by' => $this->owner->id,
        'role' => TeamRole::Admin,
    ]);

    $this->actingAs($this->owner)
        ->delete(route('teams.invitations.destroy', [$this->team, $invitation]))
        ->assertRedirect(route('teams.edit', $this->team));

    expect(TeamInvitation::find($invitation->id))->toBeNull();
});

// --- What the settings page offers ----------------------------------------------------

test('the settings page only offers an admin what D-077 allows', function () {
    $invitation = TeamInvitation::factory()->create([
        'team_id' => $this->team->id,
        'invited_by' => $this->owner->id,
        'role' => TeamRole::Admin,
    ]);

    $this->actingAs($this->admin)
        ->get(route('teams.edit', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableRoles', [
                ['value' => 'member', 'label' => 'Member'],
                ['value' => 'viewer', 'label' => 'Viewer'],
            ])
            ->where('members', fn ($members) => collect($members)->every(function (array $row) {
                $manageable = in_array($row['id'], [$this->member->id, $this->viewer->id], true);

                return $row['can_update'] === $manageable && $row['can_remove'] === $manageable;
            }))
            ->where('invitations.0.code', $invitation->code)
            ->where('invitations.0.can_cancel', false));
});

test('the settings page offers the owner every member but themselves', function () {
    $this->actingAs($this->owner)
        ->get(route('teams.edit', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('availableRoles', [
                ['value' => 'admin', 'label' => 'Admin'],
                ['value' => 'member', 'label' => 'Member'],
                ['value' => 'viewer', 'label' => 'Viewer'],
            ])
            ->where('members', fn ($members) => collect($members)->every(function (array $row) {
                $manageable = $row['id'] !== $this->owner->id;

                return $row['can_update'] === $manageable && $row['can_remove'] === $manageable;
            })));
});
