<?php

use App\Actions\Transactions\CreateTransferAction;
use App\Actions\Transactions\UpdateTransferAction;
use App\Enums\CategoryKind;
use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->creator = teamUser($this->team, TeamRole::Member);

    [$this->accountA, $this->accountB, $this->accountC, $this->accountD] = Account::factory()
        ->count(4)
        ->create([
            'team_id' => $this->team->id,
            'initial_balance' => '1000.00',
        ])
        ->all();

    $this->action = app(UpdateTransferAction::class);

    // Transferencia de 100 de A → B: A queda en 900, B en 1100.
    $this->groupId = recordTransfer($this->team, $this->creator, $this->accountA, $this->accountB);
});

test('changing the amount with the same accounts rebalances both and keeps the group and creator', function () {
    $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
        'from_account_id' => $this->accountA->id,
        'to_account_id' => $this->accountB->id,
        'amount' => '250.00',
        'description' => 'Monto corregido',
    ]));

    $legs = transferLegs($this->groupId);

    expect($this->accountA->fresh()->current_balance)->toBe('750.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1250.00')
        ->and($legs['expense']->amount)->toBe('250.00')
        ->and($legs['income']->amount)->toBe('250.00')
        ->and($legs['expense']->description)->toBe('Monto corregido')
        ->and($legs['expense']->created_by)->toBe($this->creator->id)
        ->and($legs['income']->created_by)->toBe($this->creator->id);

    $this->assertDatabaseCount('transactions', 2);
});

test('changing the destination reverts the old one, credits the new one and adjusts the source', function () {
    $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
        'from_account_id' => $this->accountA->id,
        'to_account_id' => $this->accountC->id,
        'amount' => '150.00',
    ]));

    $legs = transferLegs($this->groupId);

    expect($this->accountA->fresh()->current_balance)->toBe('850.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1000.00')
        ->and($this->accountC->fresh()->current_balance)->toBe('1150.00')
        ->and($legs['expense']->account_id)->toBe($this->accountA->id)
        ->and($legs['income']->account_id)->toBe($this->accountC->id);
});

test('changing both accounts leaves all four balances consistent', function () {
    $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
        'from_account_id' => $this->accountC->id,
        'to_account_id' => $this->accountD->id,
        'amount' => '200.00',
    ]));

    $legs = transferLegs($this->groupId);

    expect($this->accountA->fresh()->current_balance)->toBe('1000.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1000.00')
        ->and($this->accountC->fresh()->current_balance)->toBe('800.00')
        ->and($this->accountD->fresh()->current_balance)->toBe('1200.00')
        ->and($legs['expense']->account_id)->toBe($this->accountC->id)
        ->and($legs['income']->account_id)->toBe($this->accountD->id)
        ->and($legs['expense']->transfer_group_id)->toBe($this->groupId)
        ->and($legs['income']->transfer_group_id)->toBe($this->groupId);

    $this->assertDatabaseCount('transactions', 2);
});

/*
 * Permisos: creador, dueño de una cuenta involucrada (vieja) o admin+.
 */

test('the owner of the old private account can edit a transfer they did not create', function () {
    $owner = teamUser($this->team, TeamRole::Member);
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $owner->id,
        'initial_balance' => '1000.00',
    ]);
    $groupId = recordTransfer($this->team, $this->creator, $this->accountC, $privateAccount);

    $this->action->execute($this->team, $owner, $groupId, updateTransferData([
        'from_account_id' => $this->accountC->id,
        'to_account_id' => $privateAccount->id,
        'amount' => '50.00',
    ]));

    expect($this->accountC->fresh()->current_balance)->toBe('950.00')
        ->and($privateAccount->fresh()->current_balance)->toBe('1050.00')
        ->and(transferLegs($groupId)['expense']->created_by)->toBe($this->creator->id);
});

test('a team role at least admin can edit a transfer they did not create', function (TeamRole $role) {
    $editor = teamUser($this->team, $role);

    $this->action->execute($this->team, $editor, $this->groupId, updateTransferData([
        'from_account_id' => $this->accountA->id,
        'to_account_id' => $this->accountB->id,
        'amount' => '50.00',
    ]));

    $legs = transferLegs($this->groupId);

    expect($this->accountA->fresh()->current_balance)->toBe('950.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1050.00')
        ->and($legs['expense']->created_by)->toBe($this->creator->id)
        ->and($legs['income']->created_by)->toBe($this->creator->id);
})->with([
    'admin' => [TeamRole::Admin],
    'owner' => [TeamRole::Owner],
]);

test('a member who is neither creator nor owner returns a 403 and balances stay intact', function () {
    $member = teamUser($this->team, TeamRole::Member);

    expect(fn () => $this->action->execute($this->team, $member, $this->groupId, updateTransferData([
        'from_account_id' => $this->accountC->id,
        'to_account_id' => $this->accountD->id,
        'amount' => '500.00',
    ])))->toThrow(AuthorizationException::class);

    $legs = transferLegs($this->groupId);

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00')
        ->and($this->accountC->fresh()->current_balance)->toBe('1000.00')
        ->and($this->accountD->fresh()->current_balance)->toBe('1000.00')
        ->and($legs['expense']->account_id)->toBe($this->accountA->id)
        ->and($legs['expense']->amount)->toBe('100.00');
});

/*
 * Cuentas archivadas.
 */

test('an archived old account returns a 422 error', function (string $archived, string $errorKey) {
    $this->{$archived}->forceFill(['archived' => true])->save();

    try {
        $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
            'from_account_id' => $this->accountC->id,
            'to_account_id' => $this->accountD->id,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey($errorKey);
    }

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00')
        ->and($this->accountC->fresh()->current_balance)->toBe('1000.00')
        ->and($this->accountD->fresh()->current_balance)->toBe('1000.00');
})->with([
    'old source' => ['accountA', 'from_account_id'],
    'old destination' => ['accountB', 'to_account_id'],
]);

test('an archived new account returns a 422 error', function () {
    $archivedAccount = Account::factory()->archived()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);

    try {
        $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
            'from_account_id' => $this->accountA->id,
            'to_account_id' => $archivedAccount->id,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey('to_account_id');
    }

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00')
        ->and($archivedAccount->fresh()->current_balance)->toBe('1000.00');
});

/*
 * Cuentas privadas.
 */

test('moving the destination to someone else\'s private account is allowed', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);

    $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
        'from_account_id' => $this->accountA->id,
        'to_account_id' => $privateAccount->id,
    ]));

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1000.00')
        ->and($privateAccount->fresh()->current_balance)->toBe('1100.00')
        ->and(transferLegs($this->groupId)['income']->account_id)->toBe($privateAccount->id);
});

test('moving the source to someone else\'s private account returns a 403', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);

    expect(fn() => $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
        'from_account_id' => $privateAccount->id,
        'to_account_id' => $this->accountB->id,
    ])))->toThrow(AuthorizationException::class)
        ->and($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00')
        ->and($privateAccount->fresh()->current_balance)->toBe('1000.00');

});

test('moving a transfer between two private accounts returns a 422 error', function () {
    $ownPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->creator->id,
        'initial_balance' => '1000.00',
    ]);
    $otherPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);

    try {
        $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
            'from_account_id' => $ownPrivate->id,
            'to_account_id' => $otherPrivate->id,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('to_account_id');
    }

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00')
        ->and($ownPrivate->fresh()->current_balance)->toBe('1000.00')
        ->and($otherPrivate->fresh()->current_balance)->toBe('1000.00');
});

test('a new account from another team returns a 404', function () {
    $foreignAccount = Account::factory()->create([
        'team_id' => Team::factory()->create()->id,
        'initial_balance' => '500.00',
    ]);

    expect(fn() => $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
        'from_account_id' => $this->accountA->id,
        'to_account_id' => $foreignAccount->id,
    ])))->toThrow(ModelNotFoundException::class)
        ->and($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00')
        ->and($foreignAccount->fresh()->current_balance)->toBe('500.00');

});

/*
 * Categoría (ERD §4.3.17).
 */

test('going from a perimeter-crossing transfer to team to team clears the category', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);
    $category = makeCategory($this->team, CategoryKind::Expense);
    $groupId = app(CreateTransferAction::class)->execute($this->team, $this->creator, transferData([
        'from_account_id' => $this->accountC->id,
        'to_account_id' => $privateAccount->id,
        'category_id' => $category->id,
    ]))->transfer_group_id;

    expect(transferLegs($groupId)['expense']->category_id)->toBe($category->id);

    $this->action->execute($this->team, $this->creator, $groupId, updateTransferData([
        'from_account_id' => $this->accountC->id,
        'to_account_id' => $this->accountD->id,
    ]));

    $legs = transferLegs($groupId);

    expect($legs['expense']->category_id)->toBeNull()
        ->and($legs['income']->category_id)->toBeNull()
        ->and($privateAccount->fresh()->current_balance)->toBe('1000.00')
        ->and($this->accountD->fresh()->current_balance)->toBe('1100.00');
});

test('a category whose kind does not match the team leg returns a 422 error', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);

    try {
        $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
            'from_account_id' => $this->accountA->id,
            'to_account_id' => $privateAccount->id,
            'category_id' => makeCategory($this->team, CategoryKind::Income)->id,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('category_id');
    }

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00')
        ->and($privateAccount->fresh()->current_balance)->toBe('1000.00');
});

/*
 * Validación de entrada: corre antes de abrir la transacción, así que un grupo
 * inexistente responde 422 y no 404.
 */

test('a future date returns a 422 error before looking up the transfer', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'UTC'));

    try {
        $this->action->execute($this->team, $this->creator, '00000000-0000-0000-0000-000000000000', updateTransferData([
            'from_account_id' => $this->accountA->id,
            'to_account_id' => $this->accountB->id,
            'date' => CarbonImmutable::parse('2026-10-10'),
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('date');
    }
});

test('the same source and destination returns a 422 error before looking up the transfer', function () {
    try {
        $this->action->execute($this->team, $this->creator, '00000000-0000-0000-0000-000000000000', updateTransferData([
            'from_account_id' => $this->accountA->id,
            'to_account_id' => $this->accountA->id,
        ]));

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('from_account_id');
    }

    expect($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00');
});

test('a transfer group with only one leg returns a 404', function () {
    // Query builder delete: bypasses the action, so balances are not reverted.
    Transaction::where('transfer_group_id', $this->groupId)
        ->where('type', TransactionType::Income)
        ->delete();

    expect(fn () => $this->action->execute($this->team, $this->creator, $this->groupId, updateTransferData([
        'from_account_id' => $this->accountA->id,
        'to_account_id' => $this->accountB->id,
        'amount' => '50.00',
    ])))->toThrow(ModelNotFoundException::class)
        ->and($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00');

    $this->assertDatabaseCount('transactions', 1);
});

test('a member cannot gain permission by moving the transfer into their own private account', function () {
    $intruder = teamUser($this->team, TeamRole::Member);
    $intruderPrivate = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $intruder->id,
        'initial_balance' => '1000.00',
    ]);

    expect(fn() => $this->action->execute($this->team, $intruder, $this->groupId, updateTransferData([
        'from_account_id' => $this->accountA->id,
        'to_account_id' => $intruderPrivate->id,
    ])))->toThrow(AuthorizationException::class)
        ->and($this->accountA->fresh()->current_balance)->toBe('900.00')
        ->and($this->accountB->fresh()->current_balance)->toBe('1100.00')
        ->and($intruderPrivate->fresh()->current_balance)->toBe('1000.00');

});
