<?php

use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->creator = teamUser($this->team, TeamRole::Member);

    [$this->accountA, $this->accountB] = Account::factory()
        ->count(2)
        ->create([
            'team_id' => $this->team->id,
            'initial_balance' => '1000.00',
        ])
        ->all();
});

test('clean teams finish successfully without reporting anything', function () {
    recordExpense($this->team, $this->accountA, $this->creator);
    recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);

    $this->artisan('nestly:reconcile')
        ->doesntExpectOutputToContain("Team {$this->team->slug}")
        ->assertSuccessful();
});

test('a drifted balance is reported and left untouched without --fix', function () {
    recordExpense($this->team, $this->accountA, $this->creator);
    driftBalance($this->accountA, '1234.56');

    $this->artisan('nestly:reconcile')
        ->expectsOutputToContain("Team {$this->team->slug}")
        ->expectsTable(['Account', 'Expected', 'Actual'], [[$this->accountA->id, '900.00', '1234.56']])
        ->doesntExpectOutputToContain('Fixed')
        ->assertFailed();

    expect($this->accountA->fresh()->current_balance)->toBe('1234.56');
});

test('--fix rewrites drifted balances and finishes successfully', function () {
    recordExpense($this->team, $this->accountA, $this->creator);
    driftBalance($this->accountA, '1234.56');

    $this->artisan('nestly:reconcile', ['--fix' => true])
        ->expectsOutputToContain("Fixed {$this->accountA->id}: 1234.56 → 900.00")
        ->assertSuccessful();

    expect($this->accountA->fresh()->current_balance)->toBe('900.00');
});

test('--fix still fails when a transfer group is broken', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);

    DB::table('transactions')
        ->where('transfer_group_id', $groupId)
        ->where('type', TransactionType::Income->value)
        ->update(['amount' => '99.99']);

    $this->artisan('nestly:reconcile', ['--fix' => true])
        ->expectsOutputToContain($groupId)
        ->assertFailed();
});

test('--fix still fails when a private account has loose transactions', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->creator->id,
        'initial_balance' => '1000.00',
    ]);

    $transaction = recordExpense($this->team, $this->accountA, $this->creator);

    DB::table('transactions')
        ->where('id', $transaction->id)
        ->update(['account_id' => $privateAccount->id]);

    $this->artisan('nestly:reconcile', ['--fix' => true])
        ->expectsOutputToContain($transaction->id)
        ->assertFailed();
});

test('--team only reconciles the given team', function () {
    $otherTeam = Team::factory()->create();
    $otherAccount = Account::factory()->create([
        'team_id' => $otherTeam->id,
        'initial_balance' => '1000.00',
    ]);
    driftBalance($this->accountA, '1.00');
    driftBalance($otherAccount, '1234.56');

    $this->artisan('nestly:reconcile', ['--team' => $this->team->slug, '--fix' => true])
        ->expectsOutputToContain("Team {$this->team->slug}")
        ->doesntExpectOutputToContain("Team {$otherTeam->slug}")
        ->assertSuccessful();

    expect($this->accountA->fresh()->current_balance)->toBe('1000.00')
        ->and($otherAccount->fresh()->current_balance)->toBe('1234.56');
});

test('an unknown --team fails with an error', function () {
    $this->artisan('nestly:reconcile', ['--team' => 'no-existe'])
        ->expectsOutputToContain("No existe ningún team con el slug 'no-existe'")
        ->assertFailed();
});
