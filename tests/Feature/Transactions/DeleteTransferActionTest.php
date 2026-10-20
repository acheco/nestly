<?php

use App\Actions\Transactions\DeleteTransferAction;
use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->creator = User::factory()->create();
    $this->team->members()->attach($this->creator, ['role' => TeamRole::Member->value]);

    $this->fromAccount = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
    $this->toAccount = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
    $this->action = app(DeleteTransferAction::class);
});

test('the creator can delete a transfer and both balances are restored', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->fromAccount, $this->toAccount);

    expect($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');

    $this->action->execute($this->team, $this->creator, $groupId);

    expect($this->fromAccount->fresh()->current_balance)->toBe('1000.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1000.00');
    $this->assertDatabaseCount('transactions', 0);
});

test('the owner of the private destination account can delete a transfer they did not create', function () {
    $owner = teamUser($this->team, TeamRole::Member);
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $owner->id,
        'initial_balance' => '1000.00',
    ]);
    $groupId = recordTransfer($this->team, $this->creator, $this->fromAccount, $privateAccount);

    $this->action->execute($this->team, $owner, $groupId);

    expect($this->fromAccount->fresh()->current_balance)->toBe('1000.00')
        ->and($privateAccount->fresh()->current_balance)->toBe('1000.00');
    $this->assertDatabaseCount('transactions', 0);
});

test('an admin who is neither creator nor owner can delete a transfer', function () {
    $admin = teamUser($this->team, TeamRole::Admin);
    $groupId = recordTransfer($this->team, $this->creator, $this->fromAccount, $this->toAccount);

    $this->action->execute($this->team, $admin, $groupId);

    expect($this->fromAccount->fresh()->current_balance)->toBe('1000.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1000.00');
    $this->assertDatabaseCount('transactions', 0);
});

test('a non-existent transfer group returns a 404', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->fromAccount, $this->toAccount);

    expect(fn () => $this->action->execute($this->team, $this->creator, '00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class)
        ->and($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');

    $this->assertDatabaseCount('transactions', 2);
    $this->assertDatabaseHas('transactions', ['transfer_group_id' => $groupId]);
});

test('a transfer from another team returns a 404', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->fromAccount, $this->toAccount);
    $otherTeam = Team::factory()->create();
    $otherTeam->members()->attach($this->creator, ['role' => TeamRole::Admin->value]);

    expect(fn () => $this->action->execute($otherTeam, $this->creator, $groupId))
        ->toThrow(ModelNotFoundException::class)
        ->and($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');

    $this->assertDatabaseCount('transactions', 2);
});

test('a member who is neither creator nor owner returns a 403', function () {
    $member = teamUser($this->team, TeamRole::Member);
    $groupId = recordTransfer($this->team, $this->creator, $this->fromAccount, $this->toAccount);

    expect(fn () => $this->action->execute($this->team, $member, $groupId))
        ->toThrow(AuthorizationException::class)
        ->and($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');

    $this->assertDatabaseCount('transactions', 2);
});

test('a transfer from an archived source account returns a 422 error', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->fromAccount, $this->toAccount);
    $this->fromAccount->forceFill(['archived' => true])->save();

    try {
        $this->action->execute($this->team, $this->creator, $groupId);

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey('account_id');
    }

    expect($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');
    $this->assertDatabaseCount('transactions', 2);
});

test('a transfer to an archived destination account returns a 422 error', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->fromAccount, $this->toAccount);
    $this->toAccount->forceFill(['archived' => true])->save();

    try {
        $this->action->execute($this->team, $this->creator, $groupId);

        $this->fail('Expected a ValidationException to be thrown.');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->errors())->toHaveKey('account_id');
    }

    expect($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');
    $this->assertDatabaseCount('transactions', 2);
});

test('the policy denies deleteTransfer to a viewer', function () {
    $viewer = teamUser($this->team, TeamRole::Viewer);

    expect($viewer->can('deleteTransfer', [Transaction::class, $this->team]))->toBeFalse()
        ->and($this->creator->can('deleteTransfer', [Transaction::class, $this->team]))->toBeTrue();
});

test('a transfer with only one leg returns a 404', function () {
    $groupId = recordTransfer($this->team, $this->creator, $this->fromAccount, $this->toAccount);

    // Query builder delete: bypasses the action, so balances are not reverted.
    Transaction::where('transfer_group_id', $groupId)
        ->where('type', TransactionType::Income)
        ->delete();

    expect(fn () => $this->action->execute($this->team, $this->creator, $groupId))
        ->toThrow(ModelNotFoundException::class)
        ->and($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');

    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseHas('transactions', [
        'transfer_group_id' => $groupId,
        'type' => TransactionType::Expense->value,
    ]);
});
