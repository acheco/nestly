<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->team = Team::factory()->create(['currency' => 'DOP']);

    $this->owner = teamUser($this->team, TeamRole::Owner);
    $this->admin = teamUser($this->team, TeamRole::Admin);
    $this->member = teamUser($this->team, TeamRole::Member);
    $this->viewer = teamUser($this->team, TeamRole::Viewer);

    // Names fix the order: active accounts first, then by name.
    $this->teamAccount = Account::factory()->create([
        'team_id' => $this->team->id,
        'name' => 'A Joint',
        'initial_balance' => '1000.00',
    ]);
    $this->memberPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->member->id,
        'name' => 'B Member savings',
        'initial_balance' => '500.00',
    ]);
    $this->archivedAccount = Account::factory()->archived()->create([
        'team_id' => $this->team->id,
        'name' => 'A Old account',
    ]);
});

test('guests are redirected to the login page', function () {
    $this->get(route('accounts.index', $this->team))
        ->assertRedirect(route('login'));
});

test('a non member gets 403', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('accounts.index', $this->team))
        ->assertForbidden();
});

test('the page lists only the current team accounts, active first and then by name', function () {
    $otherTeam = Team::factory()->create();
    Account::factory()->create(['team_id' => $otherTeam->id, 'name' => 'A Foreign']);

    $this->actingAs($this->member)
        ->get(route('accounts.index', $this->team))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('accounts/Index')
            ->where('currency', 'DOP')
            ->has('accounts', 3)
            ->where('accounts.0.name', 'A Joint')
            ->where('accounts.1.name', 'B Member savings')
            ->where('accounts.2.name', 'A Old account')
            ->where('accounts.2.archived', true)
        );
});

test('an account is serialized with the expected shape', function () {
    $this->actingAs($this->member)
        ->get(route('accounts.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->has('accounts.0', fn (Assert $account) => $account
                ->where('id', $this->teamAccount->id)
                ->where('name', 'A Joint')
                ->where('type', $this->teamAccount->type->value)
                ->where('visibility', 'team')
                ->where('archived', false)
                ->where('owner', null)
                ->where('initialBalance', '1000.00')
                ->where('currentBalance', '1000.00')
                ->has('can', fn (Assert $can) => $can
                    ->hasAll(['update', 'archive', 'delete'])
                )
            )
        );
});

test('the owner of a private account sees its balance', function () {
    $this->actingAs($this->member)
        ->get(route('accounts.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.1.visibility', 'private')
            ->where('accounts.1.owner.id', $this->member->id)
            ->where('accounts.1.owner.name', $this->member->name)
            ->where('accounts.1.initialBalance', '500.00')
            ->where('accounts.1.currentBalance', '500.00')
        );
});

test('other members see the name of a private account but not its balance', function (string $who) {
    $this->actingAs($this->{$who})
        ->get(route('accounts.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('accounts.1.name', 'B Member savings')
            ->where('accounts.1.owner.id', $this->member->id)
            ->where('accounts.1.initialBalance', null)
            ->where('accounts.1.currentBalance', null)
        );
})->with(['owner', 'admin', 'viewer']);

test('a viewer gets every action flag set to false', function () {
    $this->actingAs($this->viewer)
        ->get(route('accounts.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createTeamAccount', false)
            ->where('can.createPrivateAccount', false)
            ->where('accounts.0.can', ['update' => false, 'archive' => false, 'delete' => false])
            ->where('accounts.1.can', ['update' => false, 'archive' => false, 'delete' => false])
        );
});

test('an admin manages team accounts but not someone else\'s private account', function () {
    $this->actingAs($this->admin)
        ->get(route('accounts.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createTeamAccount', true)
            ->where('can.createPrivateAccount', true)
            ->where('accounts.0.can', ['update' => true, 'archive' => true, 'delete' => true])
            ->where('accounts.1.can', ['update' => false, 'archive' => false, 'delete' => false])
        );
});

test('a member manages their own private account but not team accounts', function () {
    $this->actingAs($this->member)
        ->get(route('accounts.index', $this->team))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createTeamAccount', false)
            ->where('can.createPrivateAccount', true)
            ->where('accounts.0.can', ['update' => false, 'archive' => false, 'delete' => false])
            ->where('accounts.1.can', ['update' => true, 'archive' => true, 'delete' => true])
        );
});

test('private accounts cannot be created in a personal team', function () {
    $personal = Team::factory()->personal()->create();
    $owner = teamUser($personal, TeamRole::Owner);

    $this->actingAs($owner)
        ->get(route('accounts.index', $personal))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.createTeamAccount', true)
            ->where('can.createPrivateAccount', false)
        );
});

test('the number of queries does not grow with the number of accounts', function () {
    // EnsureTeamMembership switches the current team on the first visit (one extra
    // UPDATE); switching beforehand keeps both measurements on equal footing.
    $this->member->switchTeam($this->team);
    $this->actingAs($this->member);
    $url = route('accounts.index', $this->team);

    $withThree = countQueries(fn () => $this->get($url)->assertOk());

    Account::factory()->count(5)->create(['team_id' => $this->team->id]);
    Account::factory()->private()->count(5)->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->member->id,
    ]);

    $withThirteen = countQueries(fn () => $this->get($url)->assertOk());

    expect($withThirteen)->toBe($withThree);
});
