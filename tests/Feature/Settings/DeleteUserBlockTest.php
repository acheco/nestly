<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Category;
use App\Models\RecurringExpense;
use App\Models\Team;
use App\Models\User;

function deleteAccount(User $user, string $password = 'password')
{
    return test()->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => $password]);
}

function privateAccountFor(User $user, Team $team, array $attributes = []): Account
{
    return Account::factory()->private()->for($team)->create(['owner_id' => $user->id, ...$attributes]);
}

function recurringExpenseBy(User $user, Team $team, array $attributes = []): RecurringExpense
{
    return RecurringExpense::factory()->create([
        'team_id' => $team->id,
        'account_id' => Account::factory()->for($team)->create()->id,
        'category_id' => Category::where('team_id', $team->id)->firstOrFail()->id,
        'created_by' => $user->id,
        ...$attributes,
    ]);
}

function assertDeletionBlocked($response, User $user, string $reason): void
{
    $response->assertRedirect(route('profile.edit'))->assertSessionHasErrors('account');

    expect(session('errors')->first('account'))->toContain($reason);
    expect(User::find($user->id))->not->toBeNull();

    test()->assertAuthenticatedAs($user);
}

test('a user with a private account cannot be deleted', function (array $attributes) {
    $team = Team::factory()->create();
    teamUser($team, TeamRole::Owner);
    $user = teamUser($team, TeamRole::Member);
    $account = privateAccountFor($user, $team, $attributes);

    assertDeletionBlocked(deleteAccount($user), $user, 'cuentas privadas');

    $this->assertModelExists($account);
    expect($user->fresh()->belongsToTeam($team))->toBeTrue();
})->with([
    'active' => [[]],
    'archived' => [['archived' => true]],
]);

test('a private account in a deleted team still blocks the deletion', function () {
    $team = Team::factory()->create();
    teamUser($team, TeamRole::Owner);
    $user = teamUser($team, TeamRole::Member);
    privateAccountFor($user, $team);

    $team->delete();

    assertDeletionBlocked(deleteAccount($user), $user, 'cuentas privadas');
});

test('a private account in a team the user left still blocks the deletion', function () {
    $team = Team::factory()->create();
    teamUser($team, TeamRole::Owner);
    $user = teamUser($team, TeamRole::Member);
    privateAccountFor($user, $team, ['archived' => true]);

    $team->memberships()->where('user_id', $user->id)->delete();

    assertDeletionBlocked(deleteAccount($user), $user, 'cuentas privadas');
});

test('a user who created a recurring expense cannot be deleted', function (array $attributes) {
    $team = Team::factory()->create();
    teamUser($team, TeamRole::Owner);
    $user = teamUser($team, TeamRole::Member);
    $recurring = recurringExpenseBy($user, $team, $attributes);

    assertDeletionBlocked(deleteAccount($user), $user, 'gastos recurrentes');

    $this->assertModelExists($recurring);
})->with([
    'active' => [[]],
    'inactive' => [['active' => false]],
]);

test('the only owner of a shared team cannot be deleted', function (bool $withOtherMembers) {
    $team = Team::factory()->create();
    $user = teamUser($team, TeamRole::Owner);

    if ($withOtherMembers) {
        teamUser($team, TeamRole::Admin);
    }

    assertDeletionBlocked(deleteAccount($user), $user, 'único owner');

    expect($user->fresh()->belongsToTeam($team))->toBeTrue();
})->with([
    'alone' => [false],
    'with other members' => [true],
]);

test('the block message lists every reason that applies', function () {
    $team = Team::factory()->create();
    $user = teamUser($team, TeamRole::Owner);
    privateAccountFor($user, $team);
    recurringExpenseBy($user, $team);

    deleteAccount($user)->assertSessionHasErrors('account');

    expect(session('errors')->first('account'))
        ->toContain('cuentas privadas')
        ->toContain('gastos recurrentes')
        ->toContain('único owner');
});

test('a wrong password is reported before any block', function () {
    $team = Team::factory()->create();
    $user = teamUser($team, TeamRole::Owner);

    deleteAccount($user, 'wrong-password')
        ->assertSessionHasErrors('password')
        ->assertSessionDoesntHaveErrors('account');
});

test('a user without blockers can still be deleted', function (TeamRole $role) {
    $team = Team::factory()->create();
    teamUser($team, TeamRole::Owner);
    $user = teamUser($team, $role);

    deleteAccount($user)->assertSessionHasNoErrors()->assertRedirect(route('home'));

    $this->assertGuest();
    expect(User::find($user->id))->toBeNull();
    expect($team->memberships()->where('user_id', $user->id)->exists())->toBeFalse();
})->with([TeamRole::Admin, TeamRole::Member, TeamRole::Viewer]);

test('a co-owner can be deleted because the team keeps an owner', function () {
    $team = Team::factory()->create();
    $otherOwner = teamUser($team, TeamRole::Owner);
    $user = teamUser($team, TeamRole::Owner);

    deleteAccount($user)->assertSessionHasNoErrors();

    expect(User::find($user->id))->toBeNull();
    expect($team->fresh()->owner()?->is($otherOwner))->toBeTrue();
});

test('owning a deleted shared team does not block the deletion', function () {
    $team = Team::factory()->create();
    $user = teamUser($team, TeamRole::Owner);

    $team->delete();

    deleteAccount($user)->assertSessionHasNoErrors();

    expect(User::find($user->id))->toBeNull();
});

test('a remembered user stays deleted after logging out', function () {
    $user = User::factory()->create(['remember_token' => 'some-token']);

    deleteAccount($user)->assertSessionHasNoErrors();

    $this->assertGuest();
    expect(User::find($user->id))->toBeNull();
});

test('deleting a user keeps the team transactions and balances', function () {
    $team = Team::factory()->create();
    teamUser($team, TeamRole::Owner);
    $user = teamUser($team, TeamRole::Member);
    $account = Account::factory()->for($team)->create();
    $transaction = recordExpense($team, $account, $user);
    $balance = $account->fresh()->current_balance;

    deleteAccount($user)->assertSessionHasNoErrors();

    expect($transaction->fresh()->created_by)->toBeNull();
    expect($account->fresh()->current_balance)->toBe($balance);
});
