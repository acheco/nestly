<?php

use App\Actions\Teams\RemoveTeamMemberAction;
use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->owner = teamUser($this->team, TeamRole::Owner);
    $this->member = teamUser($this->team, TeamRole::Member);

    $this->teamAccount = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);

    $this->memberPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->member->id,
        'initial_balance' => '1000.00',
    ]);

    // A reimbursement into the member's private account: history that must survive.
    $this->groupId = recordTransfer($this->team, $this->owner, $this->teamAccount, $this->memberPrivate);
});

test('leaving a team archives the member\'s private accounts in that team', function () {
    $this->actingAs($this->member)
        ->delete(route('teams.leave', $this->team))
        ->assertRedirect(route('teams.index'));

    expect($this->member->fresh()->belongsToTeam($this->team))->toBeFalse()
        ->and($this->memberPrivate->fresh()->archived)->toBeTrue()
        ->and($this->memberPrivate->fresh()->owner_id)->toBe($this->member->id);
});

test('being removed by the owner archives the member\'s private accounts in that team', function () {
    $this->actingAs($this->owner)
        ->delete(route('teams.members.destroy', [$this->team, $this->member]))
        ->assertRedirect(route('teams.edit', $this->team));

    expect($this->member->fresh()->belongsToTeam($this->team))->toBeFalse()
        ->and($this->memberPrivate->fresh()->archived)->toBeTrue()
        ->and($this->memberPrivate->fresh()->owner_id)->toBe($this->member->id);
});

test('only the leaving member\'s private accounts in that team are archived', function () {
    $otherMember = teamUser($this->team, TeamRole::Member);
    $otherPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $otherMember->id,
    ]);

    // The same member's private account in another team they stay in.
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($this->member, ['role' => TeamRole::Member->value]);
    $privateElsewhere = Account::factory()->private()->create([
        'team_id' => $otherTeam->id,
        'owner_id' => $this->member->id,
    ]);

    app(RemoveTeamMemberAction::class)->execute($this->team, $this->member);

    expect($this->memberPrivate->fresh()->archived)->toBeTrue()
        ->and($this->teamAccount->fresh()->archived)->toBeFalse()
        ->and($otherPrivate->fresh()->archived)->toBeFalse()
        ->and($privateElsewhere->fresh()->archived)->toBeFalse();
});

test('the team\'s history and balances are left untouched', function () {
    $teamBalance = $this->teamAccount->fresh()->current_balance;
    $privateBalance = $this->memberPrivate->fresh()->current_balance;

    app(RemoveTeamMemberAction::class)->execute($this->team, $this->member);

    expect(Transaction::where('transfer_group_id', $this->groupId)->count())->toBe(2)
        ->and($this->teamAccount->fresh()->current_balance)->toBe($teamBalance)
        ->and($this->memberPrivate->fresh()->current_balance)->toBe($privateBalance);
});

test('a member without private accounts leaves cleanly', function () {
    $viewer = teamUser($this->team, TeamRole::Viewer);

    app(RemoveTeamMemberAction::class)->execute($this->team, $viewer);

    expect($viewer->fresh()->belongsToTeam($this->team))->toBeFalse()
        ->and($this->memberPrivate->fresh()->archived)->toBeFalse();
});

test('a former member can neither manage nor see the balance of their archived private account', function () {
    // PRD §1.6.3: once the owner is out of the team, nobody sees that balance, the owner included.
    app(RemoveTeamMemberAction::class)->execute($this->team, $this->member);

    $formerMember = $this->member->fresh();
    $account = $this->memberPrivate->fresh();

    expect($formerMember->can('update', $account))->toBeFalse()
        ->and($formerMember->can('viewBalance', $account))->toBeFalse();
});
