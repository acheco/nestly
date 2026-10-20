<?php

use App\Actions\Transactions\CreateTransactionAction;
use App\Actions\Transactions\CreateTransferAction;
use App\Actions\Transactions\DeleteTransactionAction;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->accountA = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
    $this->accountB = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
    $this->action = app(DeleteTransactionAction::class);

    // Gasto de 100 en A → A queda en 900.
    $this->transaction = recordExpense($this->team, $this->accountA, $this->user);
});

test('deleting an expense restores the account balance', function () {
    $this->action->execute($this->team, $this->transaction->id);

    expect($this->accountA->fresh()->current_balance)->toBe('1000.00');
    $this->assertModelMissing($this->transaction);
});

test('deleting an income subtracts it from the account balance', function () {
    $income = app(CreateTransactionAction::class)->execute($this->team, $this->user, transactionData([
        'account_id' => $this->accountA->id,
        'type' => TransactionType::Income,
        'amount' => '250.00',
    ]));

    $this->action->execute($this->team, $income->id);

    // 1000 − 100 (gasto inicial)
    expect($this->accountA->fresh()->current_balance)->toBe('900.00');
    $this->assertModelMissing($income);
});

test('deleting an old transaction does not touch later ones', function () {
    $later = app(CreateTransactionAction::class)->execute($this->team, $this->user, transactionData([
        'account_id' => $this->accountA->id,
        'amount' => '50.00',
        'date' => CarbonImmutable::parse('2026-09-20'),
    ]));

    $this->action->execute($this->team, $this->transaction->id);

    // 1000 − 50
    expect($this->accountA->fresh()->current_balance)->toBe('950.00')
        ->and($later->fresh()->amount)->toBe('50.00');
});

test('deleting a transaction does not affect other accounts', function () {
    recordExpense($this->team, $this->accountB, $this->user);

    $this->action->execute($this->team, $this->transaction->id);

    expect($this->accountA->fresh()->current_balance)->toBe('1000.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('900.00');
});

test('a leg of a transfer cannot be deleted as a transaction', function () {
    $transfer = app(CreateTransferAction::class)->execute($this->team, $this->user, transferData([
        'from_account_id' => $this->accountA->id,
        'to_account_id' => $this->accountB->id,
    ]));

    try {
        $this->action->execute($this->team, $transfer->expense_transaction->id);

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('account_id');
    }

    // 900 tras el gasto inicial, −100 por la transferencia
    expect($this->accountA->fresh()->current_balance)->toBe('800.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00');
    $this->assertModelExists($transfer->expense_transaction);
    $this->assertModelExists($transfer->income_transaction);
});

test('a transaction on an archived account cannot be deleted', function () {
    $this->accountA->forceFill(['archived' => true])->save();

    try {
        $this->action->execute($this->team, $this->transaction->id);

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('account_id');
    }

    expect($this->accountA->fresh()->current_balance)->toBe('900.00');
    $this->assertModelExists($this->transaction);
});

test('a transaction from another team returns a 404', function () {
    $otherTeam = Team::factory()->create();

    expect(fn () => $this->action->execute($otherTeam, $this->transaction->id))
        ->toThrow(ModelNotFoundException::class)
        ->and($this->accountA->fresh()->current_balance)->toBe('900.00');

    $this->assertModelExists($this->transaction);
});

test('a non-existent transaction returns a 404', function () {
    expect(fn () => $this->action->execute($this->team, '00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class)
        ->and($this->accountA->fresh()->current_balance)->toBe('900.00');

});
