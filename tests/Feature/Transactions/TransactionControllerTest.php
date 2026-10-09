<?php

use App\Enums\CategoryKind;
use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create();
    $this->team->members()->attach($this->user, ['role' => TeamRole::Member->value]);

    $this->account = Account::factory()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);
    $this->category = makeCategory($this->team, CategoryKind::Expense);

    $this->payload = fn (array $overrides = []) => array_merge([
        'account_id' => $this->account->id,
        'category_id' => $this->category->id,
        'type' => TransactionType::Expense->value,
        'amount' => '100.00',
        'date' => '2026-09-15',
        'description' => 'Transacción de prueba',
        'notes' => 'Notas de prueba',
    ], $overrides);
});

test('guests are redirected to the login page', function () {
    $this->post(route('transactions.store', $this->team), ($this->payload)())
        ->assertRedirect(route('login'));

    $this->assertDatabaseCount('transactions', 0);
});

test('a member can create an expense', function () {
    $this->actingAs($this->user)
        ->from(route('dashboard', $this->team))
        ->post(route('transactions.store', $this->team), ($this->payload)())
        ->assertRedirect(route('dashboard', $this->team))
        ->assertSessionHas('success')
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseHas('transactions', [
        'team_id' => $this->team->id,
        'account_id' => $this->account->id,
        'category_id' => $this->category->id,
        'type' => TransactionType::Expense->value,
        'amount' => '100.00',
        'description' => 'Transacción de prueba',
        'notes' => 'Notas de prueba',
        'transfer_group_id' => null,
        'recurring_id' => null,
        'created_by' => $this->user->id,
    ]);

    expect($this->account->fresh()->current_balance)->toBe('900.00');
});

test('a member can create an income', function () {
    $category = makeCategory($this->team, CategoryKind::Income);

    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)([
            'type' => TransactionType::Income->value,
            'category_id' => $category->id,
            'amount' => '250.50',
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('transactions', [
        'account_id' => $this->account->id,
        'category_id' => $category->id,
        'type' => TransactionType::Income->value,
        'amount' => '250.50',
    ]);

    expect($this->account->fresh()->current_balance)->toBe('1250.50');
});

test('optional fields can be null', function (string $field) {
    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)([$field => null]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseHas('transactions', [
        'account_id' => $this->account->id,
        $field => null,
    ]);
})->with(['category_id', 'description', 'notes']);

test('optional fields can be omitted from the payload', function () {
    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), Arr::except(($this->payload)(), [
            'category_id',
            'description',
            'notes',
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseHas('transactions', [
        'account_id' => $this->account->id,
        'category_id' => null,
        'description' => null,
        'notes' => null,
    ]);
    expect($this->account->fresh()->current_balance)->toBe('900.00');
});

test('amount can be sent as a number', function () {
    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)(['amount' => 100]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 1);
    expect($this->account->fresh()->current_balance)->toBe('900.00');
});

test('a user who is not a team member gets a 403', function () {
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->post(route('transactions.store', $this->team), ($this->payload)())
        ->assertForbidden();

    $this->assertDatabaseCount('transactions', 0);
});

test('a viewer cannot create a transaction', function () {
    $viewer = User::factory()->create();
    $this->team->members()->attach($viewer, ['role' => TeamRole::Viewer->value]);

    $this->actingAs($viewer)
        ->post(route('transactions.store', $this->team), ($this->payload)())
        ->assertForbidden();

    $this->assertDatabaseCount('transactions', 0);
    expect($this->account->fresh()->current_balance)->toBe('1000.00');
});

test('validation errors are returned for invalid input', function (array $overrides, string $field) {
    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)($overrides))
        ->assertSessionHasErrors($field);

    $this->assertDatabaseCount('transactions', 0);
    expect($this->account->fresh()->current_balance)->toBe('1000.00');
})->with([
    'missing account' => [['account_id' => null], 'account_id'],
    'account not a uuid' => [['account_id' => 'not-a-uuid'], 'account_id'],
    'category not a uuid' => [['category_id' => 'not-a-uuid'], 'category_id'],
    'missing type' => [['type' => null], 'type'],
    'invalid type' => [['type' => 'transfer'], 'type'],
    'missing amount' => [['amount' => null], 'amount'],
    'non numeric amount' => [['amount' => 'abc'], 'amount'],
    'zero amount' => [['amount' => '0'], 'amount'],
    'negative amount' => [['amount' => '-10.00'], 'amount'],
    'too many decimals' => [['amount' => '10.001'], 'amount'],
    'amount too large' => [['amount' => '1000000000000.00'], 'amount'],
    'missing date' => [['date' => null], 'date'],
    'invalid date' => [['date' => 'not-a-date'], 'date'],
    'description too long' => [['description' => str_repeat('a', 256)], 'description'],
    'notes too long' => [['notes' => str_repeat('a', 256)], 'notes'],
]);

test('a category whose kind does not match the transaction type fails validation', function () {
    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)([
            'type' => TransactionType::Income->value,
        ]))
        ->assertSessionHasErrors('category_id');

    $this->assertDatabaseCount('transactions', 0);
    expect($this->account->fresh()->current_balance)->toBe('1000.00');
});

test('an account from another team returns a 404', function () {
    $foreignAccount = Account::factory()->create([
        'team_id' => Team::factory()->create()->id,
        'initial_balance' => '500.00',
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)([
            'account_id' => $foreignAccount->id,
        ]))
        ->assertNotFound();

    $this->assertDatabaseCount('transactions', 0);
    expect($foreignAccount->fresh()->current_balance)->toBe('500.00');
});

test('a category from another team returns a 404', function () {
    $foreignCategory = makeCategory(Team::factory()->create(), CategoryKind::Expense);

    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)([
            'category_id' => $foreignCategory->id,
        ]))
        ->assertNotFound();

    $this->assertDatabaseCount('transactions', 0);
    expect($this->account->fresh()->current_balance)->toBe('1000.00');
});

test('a transaction on an archived account fails validation', function () {
    $archivedAccount = Account::factory()->archived()->create([
        'team_id' => $this->team->id,
        'initial_balance' => '1000.00',
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)([
            'account_id' => $archivedAccount->id,
        ]))
        ->assertSessionHasErrors('account_id');

    $this->assertDatabaseCount('transactions', 0);
    expect($archivedAccount->fresh()->current_balance)->toBe('1000.00');
});

test('a transaction on a private account fails validation', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->user->id,
        'initial_balance' => '1000.00',
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)([
            'account_id' => $privateAccount->id,
        ]))
        ->assertSessionHasErrors('account_id');

    $this->assertDatabaseCount('transactions', 0);
    expect($privateAccount->fresh()->current_balance)->toBe('1000.00');
});

test('system-only fields sent in the payload are ignored', function () {
    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)([
            'transfer_group_id' => (string) Str::uuid(),
            'recurring_id' => (string) Str::uuid(),
        ]))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseHas('transactions', [
        'account_id' => $this->account->id,
        'transfer_group_id' => null,
        'recurring_id' => null,
    ]);
});

test('a forged transfer_group_id cannot bypass the private account rule', function () {
    $privateAccount = Account::factory()->private()->create([
        'team_id' => $this->team->id,
        'owner_id' => $this->user->id,
        'initial_balance' => '1000.00',
    ]);

    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)([
            'account_id' => $privateAccount->id,
            'transfer_group_id' => (string) Str::uuid(),
        ]))
        ->assertSessionHasErrors('account_id');

    $this->assertDatabaseCount('transactions', 0);
    expect($privateAccount->fresh()->current_balance)->toBe('1000.00');
});

test('a future date fails validation', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'UTC'));

    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)(['date' => '2026-10-10']))
        ->assertSessionHasErrors('date');

    $this->assertDatabaseCount('transactions', 0);
    expect($this->account->fresh()->current_balance)->toBe('1000.00');
});

test('the date limit uses the user timezone, not the server one', function () {
    // 02:00 UTC del 10 = 22:00 del 9 en La Paz (UTC-4).
    $this->travelTo(CarbonImmutable::parse('2026-10-10 02:00:00', 'UTC'));
    $this->user->timezone = 'America/La_Paz';
    $this->user->save();

    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)(['date' => '2026-10-10']))
        ->assertSessionHasErrors('date');

    $this->actingAs($this->user)
        ->post(route('transactions.store', $this->team), ($this->payload)(['date' => '2026-10-09']))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseCount('transactions', 1);
});
