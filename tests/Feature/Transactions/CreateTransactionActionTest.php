<?php

use App\Actions\Transactions\CreateTransactionAction;
use App\Enums\CategoryKind;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\RecurringExpense;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->otherUser = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->account = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
    $this->action = app(CreateTransactionAction::class);
    $this->privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->otherUser->id,
        'initial_balance' => '1000.00',
    ]);
});

test('an expense decreases the account balance', function () {
    $category = makeCategory($this->team, CategoryKind::Expense);

    $transaction = $this->action->execute($this->team, $this->user, transactionData([
        'account_id' => $this->account->id,
        'category_id' => $category->id,
        'type' => TransactionType::Expense,
        'amount' => '250.75',
    ]));

    expect($this->account->fresh()->current_balance)->toBe('749.25')
        ->and($transaction->team_id)->toBe($this->team->id)
        ->and($transaction->account_id)->toBe($this->account->id)
        ->and($transaction->category_id)->toBe($category->id)
        ->and($transaction->created_by)->toBe($this->user->id)
        ->and($transaction->type)->toBe(TransactionType::Expense)
        ->and($transaction->amount)->toBe('250.75');
});

test('an income increases the account balance', function () {
    $category = makeCategory($this->team, CategoryKind::Income);

    $this->action->execute($this->team, $this->user, transactionData([
        'account_id' => $this->account->id,
        'category_id' => $category->id,
        'type' => TransactionType::Income,
        'amount' => '0.10',
    ]));

    expect($this->account->fresh()->current_balance)->toBe('1000.10');
});

test('an account from another team is not found', function () {
    $foreignAccount = Account::factory()->create([
        'team_id' => Team::factory()->create()->id,
        'initial_balance' => '500.00',
    ]);

    expect(fn () => $this->action->execute($this->team, $this->user, transactionData([
        'account_id' => $foreignAccount->id,
    ])))->toThrow(ModelNotFoundException::class)
        ->and($foreignAccount->fresh()->current_balance)->toBe('500.00');

    $this->assertDatabaseCount('transactions', 0);
});

test('a category whose kind does not match the transaction type is rejected', function () {
    $category = makeCategory($this->team, CategoryKind::Income);

    try {
        $this->action->execute($this->team, $this->user, transactionData([
            'account_id' => $this->account->id,
            'category_id' => $category->id,
            'type' => TransactionType::Expense,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey('category_id');
    }

    expect($this->account->fresh()->current_balance)->toBe('1000.00');
    $this->assertDatabaseCount('transactions', 0);
});

test('a transaction can be created without a category', function () {
    $transaction = $this->action->execute($this->team, $this->user, transactionData([
        'account_id' => $this->account->id,
        'category_id' => null,
        'amount' => '40.00',
    ]));

    expect($transaction->category_id)->toBeNull()
        ->and($this->account->fresh()->current_balance)->toBe('960.00');

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'category_id' => null,
    ]);
});

test('a transaction with a category from another team is not found', function () {
    $foreignCategory = makeCategory(Team::factory()->create(), CategoryKind::Expense);

    expect(fn () => $this->action->execute($this->team, $this->user, transactionData([
        'account_id' => $this->account->id,
        'category_id' => $foreignCategory->id,
        'type' => TransactionType::Expense,
    ])))->toThrow(ModelNotFoundException::class)
        ->and($this->account->fresh()->current_balance)->toBe('1000.00');

    $this->assertDatabaseCount('transactions', 0);
});

test('a recurring transaction from another team is not found', function () {
    $otherTeam = Team::factory()->create();
    $foreignRecurring = RecurringExpense::factory()->create([
        'team_id' => $otherTeam->id,
        'account_id' => Account::factory()->create(['team_id' => $otherTeam->id])->id,
        'category_id' => makeCategory($otherTeam, CategoryKind::Expense)->id,
        'created_by' => User::factory()->create()->id,
    ]);

    expect(fn () => $this->action->execute($this->team, $this->user, transactionData([
        'account_id' => $this->account->id,
        'recurring_id' => $foreignRecurring->id,
    ])))->toThrow(ModelNotFoundException::class)
        ->and($this->account->fresh()->current_balance)->toBe('1000.00');

    $this->assertDatabaseCount('transactions', 0);
});

test('a transfer to a private account without transfer_group_id dont pass', function () {
    try {
        $this->action->execute($this->team, $this->user, transactionData([
            'account_id' => $this->privateAccount->id,
            'transfer_group_id' => null,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey('account_id');
    }

    expect($this->privateAccount->fresh()->current_balance)->toBe('1000.00');
    $this->assertDatabaseCount('transactions', 0);
});

test('a transfer to a private account with transfer_group_id pass correctly', function () {

    $this->action->execute($this->team, $this->user, transactionData([
        'account_id' => $this->privateAccount->id,
        'amount' => '100.00',
        'type' => TransactionType::Income,
        'transfer_group_id' => '01a0f9f6-7144-706b-a244-4940b7fa84c5',
    ]));

    $this->action->execute($this->team, $this->otherUser, transactionData([
        'account_id' => $this->account->id,
        'amount' => '100.00',
        'type' => TransactionType::Expense,
        'transfer_group_id' => '01a0f9f6-7144-706b-a244-4940b7fa84c5',
    ]));

    expect($this->privateAccount->fresh()->current_balance)->toBe('1100.00');
    expect($this->account->fresh()->current_balance)->toBe('900.00');
    $this->assertDatabaseCount('transactions', 2);

});

test('the owner cannot record a non-transfer transaction on their private account', function () {
    try {
        $this->action->execute($this->team, $this->otherUser, transactionData([
            'account_id' => $this->privateAccount->id,
            'transfer_group_id' => null,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey('account_id');
    }

    expect($this->privateAccount->fresh()->current_balance)->toBe('1000.00');
    $this->assertDatabaseCount('transactions', 0);
});
