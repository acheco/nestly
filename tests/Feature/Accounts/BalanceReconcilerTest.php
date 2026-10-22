<?php

use App\Actions\Accounts\BalanceReconciler;
use App\Enums\BrokenTransferReason;
use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

test('a transfer group with a single leg is reported', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);

    DB::table('transactions')
        ->where('transfer_group_id', $groupId)
        ->where('type', TransactionType::Income->value)
        ->delete();

    $groups = $this->reconciler->check($this->team)->brokenGroups;

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->transfer_group_id)->toBe($groupId)
        ->and($groups[0]->reasons)->toBe([BrokenTransferReason::LegCount]);
});

test('a loose transaction on a private account is reported', function () {
    $transaction = recordExpense($this->team, $this->accountA, $this->creator);

    DB::table('transactions')
        ->where('id', $transaction->id)
        ->update(['account_id' => $this->privateAccount->id]);

    expect($this->reconciler->check($this->team)->orphanPrivateTransactions)
        ->toBe([$transaction->id]);
});

test('a transfer group whose legs have different amounts is reported', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);

    DB::table('transactions')
        ->where('transfer_group_id', $groupId)
        ->where('type', TransactionType::Income->value)
        ->update(['amount' => '99.99']);

    $groups = $this->reconciler->check($this->team)->brokenGroups;

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->transfer_group_id)->toBe($groupId)
        ->and($groups[0]->reasons)->toBe([BrokenTransferReason::AmountMismatch]);
});

test('a transfer group whose legs have different dates is reported', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);

    DB::table('transactions')
        ->where('transfer_group_id', $groupId)
        ->where('type', TransactionType::Income->value)
        ->update(['date' => '2026-09-16']);

    $groups = $this->reconciler->check($this->team)->brokenGroups;

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->transfer_group_id)->toBe($groupId)
        ->and($groups[0]->reasons)->toBe([BrokenTransferReason::DateMismatch]);
});

test('a transfer group with two expense legs is reported', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);

    DB::table('transactions')
        ->where('transfer_group_id', $groupId)
        ->update(['type' => TransactionType::Expense->value]);

    $groups = $this->reconciler->check($this->team)->brokenGroups;

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->transfer_group_id)->toBe($groupId)
        ->and($groups[0]->reasons)->toBe([BrokenTransferReason::SameType]);
});

test('a transfer group between two private accounts is reported', function () {
    $otherPrivateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->creator->id,
        'initial_balance' => '1000.00',
    ]);

    $groupId = recordTransfer($this->team, $this->creator, $this->accountA, $this->privateAccount);

    // La action no permite privada → privada; movemos la pata de A a la otra privada.
    DB::table('transactions')
        ->where('transfer_group_id', $groupId)
        ->where('account_id', $this->accountA->id)
        ->update(['account_id' => $otherPrivateAccount->id]);

    $groups = $this->reconciler->check($this->team)->brokenGroups;

    expect($groups)->toHaveCount(1)
        ->and($groups[0]->transfer_group_id)->toBe($groupId)
        ->and($groups[0]->reasons)->toBe([BrokenTransferReason::PrivateToPrivate]);
});

test('fixBalance rewrites a drifted balance and reports the drift it fixed', function () {
    recordExpense($this->team, $this->accountA, $this->creator);
    recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);

    DB::table('accounts')
        ->where('id', $this->accountA->id)
        ->update(['current_balance' => '1234.56']);

    $drift = $this->reconciler->fixBalance($this->team, $this->accountA->id);

    expect($drift)->not->toBeNull()
        ->and($drift->account_id)->toBe($this->accountA->id)
        ->and($drift->expected)->toBe('800.00')
        ->and($drift->actual)->toBe('1234.56')
        ->and($this->accountA->fresh()->current_balance)->toBe('800.00')
        ->and($this->reconciler->check($this->team)->isClean())->toBeTrue();
});

test('fixBalance returns null and leaves a balance in sync untouched', function () {
    recordExpense($this->team, $this->accountA, $this->creator);

    $before = $this->accountA->fresh();

    expect($this->reconciler->fixBalance($this->team, $this->accountA->id))->toBeNull()
        ->and($this->accountA->fresh()->current_balance)->toBe($before->current_balance)
        ->and($this->accountA->fresh()->updated_at)->toEqual($before->updated_at);
});

test('fixBalance resets an account without transactions to its initial balance', function () {
    DB::table('accounts')
        ->where('id', $this->accountB->id)
        ->update(['current_balance' => '0.00']);

    $drift = $this->reconciler->fixBalance($this->team, $this->accountB->id);

    expect($drift->expected)->toBe('1000.00')
        ->and($drift->actual)->toBe('0.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1000.00');
});

test('fixBalance only touches the given account', function () {
    DB::table('accounts')
        ->whereIn('id', [$this->accountA->id, $this->accountB->id])
        ->update(['current_balance' => '1.00']);

    $this->reconciler->fixBalance($this->team, $this->accountA->id);

    $drifts = $this->reconciler->check($this->team)->drifts;

    expect($drifts)->toHaveCount(1)
        ->and($drifts[0]->account_id)->toBe($this->accountB->id)
        ->and($this->accountB->fresh()->current_balance)->toBe('1.00');
});

test('fixBalance fixes a private account from the transfer legs that cross into it', function () {
    recordTransfer($this->team, $this->creator, $this->accountA, $this->privateAccount);

    $expected = $this->privateAccount->fresh()->current_balance;

    DB::table('accounts')
        ->where('id', $this->privateAccount->id)
        ->update(['current_balance' => '0.00']);

    $drift = $this->reconciler->fixBalance($this->team, $this->privateAccount->id);

    expect($drift->expected)->toBe($expected)
        ->and($this->privateAccount->fresh()->current_balance)->toBe($expected);
});

test('fixBalance refuses an account from another team and leaves it untouched', function () {
    $foreignAccount = Account::factory()->create([
        'team_id' => Team::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);

    DB::table('accounts')
        ->where('id', $foreignAccount->id)
        ->update(['current_balance' => '1.00']);

    expect(fn () => $this->reconciler->fixBalance($this->team, $foreignAccount->id))
        ->toThrow(ModelNotFoundException::class)
        ->and($foreignAccount->fresh()->current_balance)->toBe('1.00');
});
