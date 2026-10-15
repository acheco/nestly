<?php

use App\Enums\CategoryKind;
use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => TeamRole::Member->value]);

    $this->fromAccount = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
    $this->toAccount = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);

    $this->payload = fn (array $overrides = []) => array_merge([
        'from_account_id' => $this->fromAccount->id,
        'to_account_id' => $this->toAccount->id,
        'amount' => '100.00',
        'description' => 'Transferencia de prueba',
        'date' => '2026-09-15',
    ], $overrides);
});

test('guests are redirected to the login page', function () {
    $this->post(route('transfers.store', $this->team), ($this->payload)())
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('transactions', 0);
});

test('a member can create a transfer', function () {
    $this->actingAs($this->user)
        ->from(route('dashboard', $this->team))
        ->post(route('transfers.store', $this->team), ($this->payload)())
        ->assertRedirect(route('dashboard', $this->team))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 2);
    $this->assertDatabaseHas('transactions', [
        'team_id' => $this->team->id,
        'account_id' => $this->fromAccount->id,
        'type' => TransactionType::Expense->value,
        'amount' => '100.00',
        'description' => 'Transferencia de prueba',
        'created_by' => $this->user->id,
    ]);
    $this->assertDatabaseHas('transactions', [
        'team_id' => $this->team->id,
        'account_id' => $this->toAccount->id,
        'type' => TransactionType::Income->value,
        'amount' => '100.00',
        'description' => 'Transferencia de prueba',
        'created_by' => $this->user->id,
    ]);

    expect($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');
});

test('description is optional', function () {
    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)(['description' => null]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 2);
});

test('description can be omitted from the payload', function () {
    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), Arr::except(($this->payload)(), 'description'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 2);
    $this->assertDatabaseHas('transactions', [
        'account_id' => $this->fromAccount->id,
        'description' => null,
    ]);
});

test('amount can be sent as a number', function () {
    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)(['amount' => 100]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 2);
    expect($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');
});

test('a user who is not a team member gets a 403', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('transfers.store', $this->team), ($this->payload)())
        ->assertForbidden();

    $this->assertDatabaseCount('transactions', 0);
});

test('a viewer cannot create a transfer', function () {
    $viewer = User::factory()->create();
    $this->team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);

    $this->actingAs($viewer)
        ->post(route('transfers.store', $this->team), ($this->payload)())
        ->assertForbidden();

    $this->assertDatabaseCount('transactions', 0);
    expect($this->fromAccount->fresh()->current_balance)->toBe('1000.00');
});

test('validation errors are returned for invalid input', function (array $overrides, string $field) {
    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)($overrides))
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('transactions', 0);
})->with([
    'missing from account' => [['from_account_id' => null], 'from_account_id'],
    'from account not a uuid' => [['from_account_id' => 'not-a-uuid'], 'from_account_id'],
    'missing to account' => [['to_account_id' => null], 'to_account_id'],
    'to account not a uuid' => [['to_account_id' => 'not-a-uuid'], 'to_account_id'],
    'missing amount' => [['amount' => null], 'amount'],
    'non numeric amount' => [['amount' => 'abc'], 'amount'],
    'zero amount' => [['amount' => '0'], 'amount'],
    'negative amount' => [['amount' => '-10.00'], 'amount'],
    'too many decimals' => [['amount' => '10.001'], 'amount'],
    'amount too large' => [['amount' => '1000000000000.00'], 'amount'],
    'description too long' => [['description' => str_repeat('a', 256)], 'description'],
    'missing date' => [['date' => null], 'date'],
    'invalid date' => [['date' => 'not-a-date'], 'date'],
    'category not a uuid' => [['category_id' => 'not-a-uuid'], 'category_id'],
]);

test('a member can create a categorized transfer to a private account', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);
    $category = makeCategory($this->team, CategoryKind::Expense);

    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)([
            'to_account_id' => $privateAccount->id,
            'category_id' => $category->id,
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'account_id' => $this->fromAccount->id,
        'category_id' => $category->id,
    ]);
    $this->assertDatabaseHas('transactions', [
        'account_id' => $privateAccount->id,
        'category_id' => null,
    ]);
});

test('a team to team transfer with a category fails validation', function () {
    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)([
            'category_id' => makeCategory($this->team, CategoryKind::Expense)->id,
        ]))
        ->assertSessionHasErrors('category_id');

    $this->assertDatabaseCount('transactions', 0);
});

test('a transfer to the same account fails validation', function () {
    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)([
            'to_account_id' => $this->fromAccount->id,
        ]))
        ->assertSessionHasErrors('from_account_id');

    $this->assertDatabaseCount('transactions', 0);
    expect($this->fromAccount->fresh()->current_balance)->toBe('1000.00');
});

test('a transfer involving an account from another team returns a 404', function (string $field) {
    $foreignAccount = Account::factory()->create([
        'team_id' => Team::factory()->create()->id,
        'initial_balance' => '500.00',
    ]);

    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)([$field => $foreignAccount->id]))
        ->assertNotFound();

    $this->assertDatabaseCount('transactions', 0);
    expect($foreignAccount->fresh()->current_balance)->toBe('500.00');
})->with(['from_account_id', 'to_account_id']);

test('a transfer from another user private account returns a 403', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => User::factory()->create()->id,
        'initial_balance' => '1000.00',
    ]);

    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)([
            'from_account_id' => $privateAccount->id,
        ]))
        ->assertForbidden();

    $this->assertDatabaseCount('transactions', 0);
    expect($privateAccount->fresh()->current_balance)->toBe('1000.00');
});

test('a transfer involving an archived account fails validation', function (string $field) {
    $archivedAccount = Account::factory()->archived()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);

    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)([$field => $archivedAccount->id]))
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('transactions', 0);
    expect($archivedAccount->fresh()->current_balance)->toBe('1000.00');
})->with(['from_account_id', 'to_account_id']);

test('a transfer with a future date fails validation', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'UTC'));

    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)(['date' => '2026-10-10']))
        ->assertSessionHasErrors('date');

    $this->assertDatabaseCount('transactions', 0);
    expect($this->fromAccount->fresh()->current_balance)->toBe('1000.00');
});

test('the transfer date limit uses the user timezone, not the server one', function () {
    // 02:00 UTC del 10 = 22:00 del 9 en La Paz (UTC-4).
    $this->travelTo(CarbonImmutable::parse('2026-10-10 02:00:00', 'UTC'));
    $this->user->timezone = 'America/La_Paz';
    $this->user->save();

    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)(['date' => '2026-10-10']))
        ->assertSessionHasErrors('date');

    $this->actingAs($this->user)
        ->post(route('transfers.store', $this->team), ($this->payload)(['date' => '2026-10-09']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 2);
});

test('guests are redirected to the login page on delete', function () {
    $transferGroupId = recordTransfer($this->team, $this->user, $this->fromAccount, $this->toAccount);

    $this->delete(route('transfers.destroy', [$this->team, $transferGroupId]))
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('transactions', 2);
    expect($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');
});

test('the creator can delete their transfer', function () {
    $transferGroupId = recordTransfer($this->team, $this->user, $this->fromAccount, $this->toAccount);

    $this->actingAs($this->user)
        ->from(route('dashboard', $this->team))
        ->delete(route('transfers.destroy', [$this->team, $transferGroupId]))
        ->assertRedirect(route('dashboard', $this->team))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 0);
    expect($this->fromAccount->fresh()->current_balance)->toBe('1000.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1000.00');
});

test('a viewer cannot delete a transfer', function () {
    $viewer = User::factory()->create();
    $this->team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);
    $transferGroupId = recordTransfer($this->team, $this->user, $this->fromAccount, $this->toAccount);

    $this->actingAs($viewer)
        ->delete(route('transfers.destroy', [$this->team, $transferGroupId]))
        ->assertForbidden();

    $this->assertDatabaseCount('transactions', 2);
    expect($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');
});

test('a member who is neither the creator nor an account owner cannot delete a transfer', function () {
    $other = User::factory()->create();
    $this->team->members()->attach($other, ['role' => TeamRole::Member->value]);
    $transferGroupId = recordTransfer($this->team, $other, $this->fromAccount, $this->toAccount);

    $this->actingAs($this->user)
        ->delete(route('transfers.destroy', [$this->team, $transferGroupId]))
        ->assertForbidden();

    $this->assertDatabaseCount('transactions', 2);
    expect($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');
});

test('a transfer from another team returns a 404 on delete', function () {
    $otherTeam = Team::factory()->create();
    $otherFrom = Account::factory()->create(['team_id' => $otherTeam->id, 'initial_balance' => '500.00']);
    $otherTo = Account::factory()->create(['team_id' => $otherTeam->id, 'initial_balance' => '500.00']);
    $foreignGroupId = recordTransfer($otherTeam, User::factory()->create(), $otherFrom, $otherTo);

    $this->actingAs($this->user)
        ->delete(route('transfers.destroy', [$this->team, $foreignGroupId]))
        ->assertNotFound();

    $this->assertDatabaseCount('transactions', 2);
    expect($otherFrom->fresh()->current_balance)->toBe('400.00')
        ->and($otherTo->fresh()->current_balance)->toBe('600.00');
});

test('a non uuid transfer id returns a 404', function () {
    // route() no genera URLs que violen whereUuid, así que se arma a mano.
    $this->actingAs($this->user)
        ->delete(route('transfers.store', $this->team).'/abc')
        ->assertNotFound();
});

test('a transfer involving an archived account cannot be deleted', function (string $account) {
    $transferGroupId = recordTransfer($this->team, $this->user, $this->fromAccount, $this->toAccount);
    $this->{$account}->forceFill(['archived' => true])->save();

    $this->actingAs($this->user)
        ->delete(route('transfers.destroy', [$this->team, $transferGroupId]))
        ->assertSessionHasErrors('account_id');

    $this->assertDatabaseCount('transactions', 2);
    expect($this->fromAccount->fresh()->current_balance)->toBe('900.00')
        ->and($this->toAccount->fresh()->current_balance)->toBe('1100.00');
})->with(['fromAccount', 'toAccount']);
