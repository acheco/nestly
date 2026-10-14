<?php

use App\Actions\Transactions\CreateTransferAction;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->fromAccount = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
    $this->toAccount = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
    $this->action = app(CreateTransferAction::class);
});

test('a user can create a transfer', function () {
    $result = $this->action->execute($this->team, $this->user, transferData([
        'from_account_id' => $this->fromAccount->id,
        'to_account_id' => $this->toAccount->id,
        'description' => 'Transfer from account one to account two',
    ]));

    $this->assertDatabaseCount('transactions', 2);

    expect($result->expense_transaction->type)->toBe(TransactionType::Expense)
        ->and($result->expense_transaction->account_id)->toBe($this->fromAccount->id)
        ->and($result->income_transaction->type)->toBe(TransactionType::Income)
        ->and($result->income_transaction->account_id)->toBe($this->toAccount->id)
        ->and($result->expense_transaction->transfer_group_id)->toBe($result->transfer_group_id)
        ->and($result->income_transaction->transfer_group_id)->toBe($result->transfer_group_id)
        ->and($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00')
        ->and($result->income_transaction->amount)->toBe('100.00')
        ->and($result->expense_transaction->amount)->toBe('100.00')
        ->and($result->expense_transaction->created_by)->toBe($this->user->id)
        ->and($result->income_transaction->created_by)->toBe($this->user->id);
});

test('a transfer to the same account returns a 422 error', function () {
    try {
        $this->action->execute($this->team, $this->user, transferData([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->fromAccount->id,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey('from_account_id')
            ->and($this->fromAccount->fresh()->current_balance)->toBe('1000.00');
    }

    $this->assertDatabaseCount('transactions', 0);
});

test('a transfer to an account from other team returns a 404', function () {
    $foreignAccount = Account::factory()->create([
        'team_id' => Team::factory()->create()->id,
        'initial_balance' => '500.00',
    ]);

    expect(fn () => $this->action->execute($this->team, $this->user, transferData([
        'from_account_id' => $this->fromAccount->id,
        'to_account_id' => $foreignAccount->id,
    ])))->toThrow(ModelNotFoundException::class)
        ->and($this->fromAccount->fresh()->current_balance)->toBe('1000.00')
        ->and($foreignAccount->fresh()->current_balance)->toBe('500.00');

    $this->assertDatabaseCount('transactions', 0);
});

test('a transfer from an account from other team returns a 404', function () {
    $foreignAccount = Account::factory()->create([
        'team_id' => Team::factory()->create()->id,
        'initial_balance' => '500.00',
    ]);

    expect(fn () => $this->action->execute($this->team, $this->user, transferData([
        'from_account_id' => $foreignAccount->id,
        'to_account_id' => $this->toAccount->id,
    ])))->toThrow(ModelNotFoundException::class)
        ->and($foreignAccount->fresh()->current_balance)->toBe('500.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1000.00');

    $this->assertDatabaseCount('transactions', 0);
});

test('a transfer from a private account from other user returns a 403', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);

    expect(fn () => $this->action->execute($this->team, $this->user, transferData([
        'from_account_id' => $privateAccount->id,
        'to_account_id' => $this->toAccount->id,
    ])))->toThrow(AuthorizationException::class)
        ->and($privateAccount->fresh()->current_balance)->toBe('1000.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1000.00');

    $this->assertDatabaseCount('transactions', 0);
});

test('a user can make a transfer from its own private account', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->user->id,
        'initial_balance' => '1000.00',
    ]);

    $result = $this->action->execute($this->team, $this->user, transferData([
        'from_account_id' => $privateAccount->id,
        'to_account_id' => $this->toAccount->id,
    ]));

    $this->assertDatabaseCount('transactions', 2);

    expect($result->expense_transaction->account_id)->toBe($privateAccount->id)
        ->and($result->income_transaction->account_id)->toBe($this->toAccount->id)
        ->and($privateAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');
});

test('a user can make a transfer to a private account', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);

    $result = $this->action->execute($this->team, $this->user, transferData([
        'from_account_id' => $this->fromAccount->id,
        'to_account_id' => $privateAccount->id,
    ]));

    $this->assertDatabaseCount('transactions', 2);

    expect($result->expense_transaction->account_id)->toBe($this->fromAccount->id)
        ->and($result->income_transaction->account_id)->toBe($privateAccount->id)
        ->and($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($privateAccount->fresh()->current_balance)->toBe('1100.00');
});

test('a transfer between two private accounts returns a 422 error', function () {
    $ownPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->user->id,
        'initial_balance' => '1000.00',
    ]);
    $otherPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);

    try {
        $this->action->execute($this->team, $this->user, transferData([
            'from_account_id' => $ownPrivate->id,
            'to_account_id' => $otherPrivate->id,
        ]));
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('to_account_id');
    }

    expect($ownPrivate->fresh()->current_balance)->toBe('1000.00')
        ->and($otherPrivate->fresh()->current_balance)->toBe('1000.00');

    $this->assertDatabaseCount('transactions', 0);
});

test('taking money from someone else\'s private account into a private one returns a 403, not a 422', function () {
    $otherPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);
    $ownPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->user->id,
        'initial_balance' => '1000.00',
    ]);

    expect(fn () => $this->action->execute($this->team, $this->user, transferData([
        'from_account_id' => $otherPrivate->id,
        'to_account_id' => $ownPrivate->id,
    ])))->toThrow(AuthorizationException::class);

    $this->assertDatabaseCount('transactions', 0);
});

test('a transfer from an archived account returns a 422 error', function () {
    $archivedAccount = Account::factory()->archived()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);

    try {
        $this->action->execute($this->team, $this->user, transferData([
            'from_account_id' => $archivedAccount->id,
            'to_account_id' => $this->toAccount->id,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey('from_account_id')
            ->and($archivedAccount->fresh()->current_balance)->toBe('1000.00')
            ->and($this->toAccount->fresh()->current_balance)->toBe('1000.00');
    }

    $this->assertDatabaseCount('transactions', 0);
});

test('a transfer to an archived account returns a 422 error', function () {
    $archivedAccount = Account::factory()->archived()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);

    try {
        $this->action->execute($this->team, $this->user, transferData([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $archivedAccount->id,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey('to_account_id')
            ->and($this->fromAccount->fresh()->current_balance)->toBe('1000.00')
            ->and($archivedAccount->fresh()->current_balance)->toBe('1000.00');
    }

    $this->assertDatabaseCount('transactions', 0);
});

test('a transfer with a future date is rejected', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'UTC'));

    try {
        $this->action->execute($this->team, $this->user, transferData([
            'from_account_id' => $this->fromAccount->id,
            'to_account_id' => $this->toAccount->id,
            'date' => CarbonImmutable::parse('2026-10-10'),
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('date');
    }

    expect($this->fromAccount->fresh()->current_balance)->toBe('1000.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1000.00');
    $this->assertDatabaseCount('transactions', 0);
});

test('the transfer date limit uses the user timezone, not the server one', function () {
    // 02:00 UTC del 10 = 22:00 del 9 en La Paz (UTC-4).
    $this->travelTo(CarbonImmutable::parse('2026-10-10 02:00:00', 'UTC'));
    $user = User::factory()->create(['timezone' => 'America/La_Paz']);

    expect(fn () => $this->action->execute($this->team, $user, transferData([
        'from_account_id' => $this->fromAccount->id,
        'to_account_id' => $this->toAccount->id,
        'date' => CarbonImmutable::parse('2026-10-10'),
    ])))->toThrow(ValidationException::class);

    $this->action->execute($this->team, $user, transferData([
        'from_account_id' => $this->fromAccount->id,
        'to_account_id' => $this->toAccount->id,
        'date' => CarbonImmutable::parse('2026-10-09'),
    ]));

    $this->assertDatabaseCount('transactions', 2);
});
