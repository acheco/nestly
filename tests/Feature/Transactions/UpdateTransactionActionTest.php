<?php

use App\Actions\Transactions\CreateTransactionAction;
use App\Actions\Transactions\CreateTransferAction;
use App\Actions\Transactions\UpdateTransactionAction;
use App\Enums\CategoryKind;
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
    $this->action = app(UpdateTransactionAction::class);

    // Gasto de 100 en A → A queda en 900.
    $this->transaction = app(CreateTransactionAction::class)->execute($this->team, $this->user, transactionData([
        'account_id' => $this->accountA->id,
        'amount' => '100.00',
    ]));
});

test('changing the amount reverts the old effect and applies the new one', function () {
    $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $this->accountA->id,
        'amount' => '150.00',
    ]));

    expect($this->accountA->fresh()->current_balance)->toBe('850.00')
        ->and($this->transaction->fresh()->amount)->toBe('150.00');
});

test('changing the type from expense to income moves the balance twice the amount', function () {
    $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $this->accountA->id,
        'type' => TransactionType::Income,
    ]));

    expect($this->accountA->fresh()->current_balance)->toBe('1100.00')
        ->and($this->transaction->fresh()->type)->toBe(TransactionType::Income);
});

test('moving a transaction to another account restores the old account and charges the new one', function () {
    $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $this->accountB->id,
    ]));

    expect($this->accountA->fresh()->current_balance)->toBe('1000.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('900.00')
        ->and($this->transaction->fresh()->account_id)->toBe($this->accountB->id);
});

test('editing an old transaction does not touch later ones', function () {
    $later = app(CreateTransactionAction::class)->execute($this->team, $this->user, transactionData([
        'account_id' => $this->accountA->id,
        'amount' => '50.00',
        'date' => CarbonImmutable::parse('2026-09-20'),
    ]));

    $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $this->accountA->id,
        'amount' => '200.00',
    ]));

    // 1000 − 200 − 50
    expect($this->accountA->fresh()->current_balance)->toBe('750.00')
        ->and($later->fresh()->amount)->toBe('50.00');
});

test('a leg of a transfer cannot be edited as a transaction', function () {
    $transfer = app(CreateTransferAction::class)->execute($this->team, $this->user, transferData([
        'from_account_id' => $this->accountA->id,
        'to_account_id' => $this->accountB->id,
    ]));

    try {
        $this->action->execute($this->team, $this->user, $transfer->expense_transaction->id, updateTransactionData([
            'account_id' => $this->accountA->id,
            'amount' => '999.00',
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('account_id');
    }

    // 900 tras el gasto inicial, −100 por la transferencia
    expect($this->accountA->fresh()->current_balance)->toBe('800.00')
        ->and($transfer->expense_transaction->fresh()->amount)->toBe('100.00');
});

test('a transaction on an archived account cannot be edited', function () {
    $this->accountA->forceFill(['archived' => true])->save();

    expect(fn () => $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $this->accountA->id,
        'amount' => '150.00',
    ])))->toThrow(ValidationException::class);

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->transaction->fresh()->amount)->toBe('100.00');
});

test('a transaction cannot be moved to an archived account', function () {
    $this->accountB->forceFill(['archived' => true])->save();

    expect(fn () => $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $this->accountB->id,
    ])))->toThrow(ValidationException::class);

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1000.00');
});

test('a transaction cannot be moved to a private account', function () {
    $private = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->user->id,
        'initial_balance' => '1000.00',
    ]);

    expect(fn () => $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $private->id,
    ])))->toThrow(ValidationException::class);

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($private->fresh()->current_balance)->toBe('1000.00');
});

test('a category whose kind does not match the type is rejected', function () {
    $income = makeCategory($this->team, CategoryKind::Income);

    expect(fn () => $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $this->accountA->id,
        'category_id' => $income->id,
        'type' => TransactionType::Expense,
    ])))->toThrow(ValidationException::class);

    expect($this->accountA->fresh()->current_balance)->toBe('900.00');
});

test('a future date is rejected', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'UTC'));

    expect(fn () => $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $this->accountA->id,
        'date' => CarbonImmutable::parse('2026-10-10'),
    ])))->toThrow(ValidationException::class);

    expect($this->accountA->fresh()->current_balance)->toBe('900.00');
});

test('a transaction from another team returns a 404', function () {
    $otherTeam = Team::factory()->create();

    expect(fn () => $this->action->execute($otherTeam, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $this->accountA->id,
    ])))->toThrow(ModelNotFoundException::class);

    expect($this->accountA->fresh()->current_balance)->toBe('900.00');
});

test('moving to an account from another team returns a 404', function () {
    $foreign = Account::factory()->create([
        'team_id' => Team::factory()->create()->id,
        'initial_balance' => '500.00',
    ]);

    expect(fn () => $this->action->execute($this->team, $this->user, $this->transaction->id, updateTransactionData([
        'account_id' => $foreign->id,
    ])))->toThrow(ModelNotFoundException::class);

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($foreign->fresh()->current_balance)->toBe('500.00');
});
