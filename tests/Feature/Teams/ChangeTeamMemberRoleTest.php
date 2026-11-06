<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\Transaction;

/*
| D-017: a viewer can't have private accounts (D-011), so demoting someone to viewer
| archives their private accounts in that team, as leaving does (D-016). Owner and
| admin can both demote (D-077). Coming back to member doesn't reactivate them.
*/

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->owner = teamUser($this->team, TeamRole::Owner);
    $this->admin = teamUser($this->team, TeamRole::Admin);
    $this->member = teamUser($this->team, TeamRole::Member);
    $this->otherMember = teamUser($this->team, TeamRole::Member);

    $this->teamAccount = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);

    $this->privateOf = fn ($user, ?Team $team = null) => Account::factory()->private()->create([
        'team_id' => ($team ?? $this->team)->id,
        'owner_id' => $user->id,
        'initial_balance' => '500.00',
    ]);

    $this->memberPrivate = ($this->privateOf)($this->member);

    // A transfer into the private account: history that must survive the archive.
    recordTransfer($this->team, $this->owner, $this->teamAccount, $this->memberPrivate);

    $this->changeRole = fn ($actor, $user, TeamRole $role) => $this->actingAs($actor)
        ->patch(route('teams.members.update', [$this->team, $user]), ['role' => $role->value]);
});

test('demoting a member to viewer archives their private accounts in that team', function (string $actor) {
    $balanceBefore = $this->memberPrivate->fresh()->current_balance;
    $transactionsBefore = Transaction::count();

    ($this->changeRole)($this->{$actor}, $this->member, TeamRole::Viewer)
        ->assertRedirect(route('teams.edit', $this->team));

    $private = $this->memberPrivate->fresh();

    expect($this->member->fresh()->teamRole($this->team))->toBe(TeamRole::Viewer)
        ->and($private->archived)->toBeTrue()
        ->and($private->owner_id)->toBe($this->member->id)
        ->and($private->current_balance)->toBe($balanceBefore)
        ->and(Transaction::count())->toBe($transactionsBefore);
})->with(['owner', 'admin']);

test('demoting an admin to viewer archives their private accounts', function () {
    $adminPrivate = ($this->privateOf)($this->admin);

    ($this->changeRole)($this->owner, $this->admin, TeamRole::Viewer)
        ->assertRedirect(route('teams.edit', $this->team));

    expect($adminPrivate->fresh()->archived)->toBeTrue();
});

test('only the demoted member\'s private accounts in that team are archived', function () {
    $otherMemberPrivate = ($this->privateOf)($this->otherMember);
    $sameMemberElsewhere = ($this->privateOf)($this->member, tap(Team::factory()->create(), function (Team $other) {
        $other->members()->attach($this->member, ['role' => TeamRole::Member->value]);
    }));

    ($this->changeRole)($this->owner, $this->member, TeamRole::Viewer);

    expect($this->memberPrivate->fresh()->archived)->toBeTrue()
        ->and($otherMemberPrivate->fresh()->archived)->toBeFalse()
        ->and($sameMemberElsewhere->fresh()->archived)->toBeFalse()
        ->and($this->teamAccount->fresh()->archived)->toBeFalse();
});

test('role changes that do not end in viewer archive nothing', function (string $target, TeamRole $newRole) {
    $private = $target === 'member' ? $this->memberPrivate : ($this->privateOf)($this->{$target});

    ($this->changeRole)($this->owner, $this->{$target}, $newRole)
        ->assertRedirect(route('teams.edit', $this->team));

    expect($private->fresh()->archived)->toBeFalse();
})->with([
    'member to admin' => ['member', TeamRole::Admin],
    'admin to member' => ['admin', TeamRole::Member],
]);

test('promoting a viewer back to member does not reactivate their private accounts', function () {
    ($this->changeRole)($this->owner, $this->member, TeamRole::Viewer);
    ($this->changeRole)($this->owner, $this->member, TeamRole::Member)
        ->assertRedirect(route('teams.edit', $this->team));

    expect($this->member->fresh()->teamRole($this->team))->toBe(TeamRole::Member)
        ->and($this->memberPrivate->fresh()->archived)->toBeTrue();
});

test('a refused demotion leaves the role and the private accounts untouched', function () {
    // A member can't change roles (D-077).
    ($this->changeRole)($this->otherMember, $this->member, TeamRole::Viewer)
        ->assertForbidden();

    expect($this->member->fresh()->teamRole($this->team))->toBe(TeamRole::Member)
        ->and($this->memberPrivate->fresh()->archived)->toBeFalse();
});
