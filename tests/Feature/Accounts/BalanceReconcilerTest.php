<?php

use App\Actions\Accounts\BalanceReconciler;
use App\Enums\TeamRole;
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

    $this->privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->creator->id,
        'initial_balance' => '1000.00',
    ]);

    $this->reconciler = app(BalanceReconciler::class);
});

test('a team whose data was written through the actions is clean', function () {
    recordExpense($this->team, $this->accountA, $this->creator);
    recordExpense($this->team, $this->accountB, $this->creator);

    recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);
    // Cruza el perímetro del team en ambos sentidos: team → privada y privada → team.
    recordTransfer($this->team, $this->creator, $this->accountB, $this->privateAccount);
    recordTransfer($this->team, $this->creator, $this->privateAccount, $this->accountA);

    $report = $this->reconciler->check($this->team);

    expect($report->isClean())->toBeTrue()
        ->and($report->drifts)->toBeEmpty();
});

test('a balance changed behind the actions\' back is reported as drift', function () {
    recordExpense($this->team, $this->accountA, $this->creator);
    recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);

    // A queda en 800.00 vía actions; lo pisamos directamente en la tabla.
    DB::table('accounts')
        ->where('id', $this->accountA->id)
        ->update(['current_balance' => '1234.56']);

    $report = $this->reconciler->check($this->team);

    expect($report->isClean())->toBeFalse()
        ->and($report->drifts)->toHaveCount(1);

    $drift = $report->drifts[0];

    expect($drift->account_id)->toBe($this->accountA->id)
        ->and($drift->expected)->toBe('800.00')
        ->and($drift->actual)->toBe('1234.56');
});
