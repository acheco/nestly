<?php

use App\Enums\TeamRole;
use App\Jobs\ReconcileAccountBalancesJob;
use App\Models\Account;
use App\Models\Team;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->creator = teamUser($this->team, TeamRole::Member);

    $this->account = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
});

test('clean teams are not logged', function () {
    recordExpense($this->team, $this->account, $this->creator);
    Log::spy();

    ReconcileAccountBalancesJob::dispatchSync();

    Log::shouldNotHaveReceived('warning');
});

test('drifted teams are logged with their slug', function () {
    recordExpense($this->team, $this->account, $this->creator);
    driftBalance($this->account, '1234.56');
    Log::spy();

    ReconcileAccountBalancesJob::dispatchSync();

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn ($message, $context) => $context['team'] === $this->team->slug
            && $context['drifts'] === [$this->account->id]
    );
});

test('drifted balances are left untouched', function () {
    recordExpense($this->team, $this->account, $this->creator);
    driftBalance($this->account, '1234.56');
    Log::spy();

    ReconcileAccountBalancesJob::dispatchSync();

    Log::shouldHaveReceived('warning')->once();
    expect($this->account->fresh()->current_balance)->toBe('1234.56');
});

test('the job is scheduled', function () {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('ReconcileAccountBalancesJob')
        ->assertSuccessful();
});

test('loose private transactions are logged', function () {
    $private = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->creator->id,
        'initial_balance' => '1000.00',
    ]);
    $transaction = recordExpense($this->team, $this->account, $this->creator);
    DB::table('transactions')->where('id', $transaction->id)->update(['account_id' => $private->id]);
    Log::spy();

    ReconcileAccountBalancesJob::dispatchSync();

    Log::shouldHaveReceived('warning')->once()->withArgs(
        fn ($message, $context) => $context['orphan_private_transactions'] === [$transaction->id]
    );
});
