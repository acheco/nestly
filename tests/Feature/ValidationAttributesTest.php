<?php

use App\Enums\CategoryKind;
use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;

// Spec 001, R3 (D-092): every field name the Form Requests validate is translated through
// `attributes` in lang/es/validation.php, so error messages never show `account_id` or `icon`.

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->owner = teamUser($this->team, TeamRole::Owner);
    $this->tooLong = str_repeat('a', 256);
});

/**
 * @return array<string, string>
 */
function transactionFieldErrors(): array
{
    return [
        'account_id' => 'El campo cuenta es obligatorio.',
        'type' => 'El campo tipo es obligatorio.',
        'amount' => 'El campo monto es obligatorio.',
        'date' => 'El campo fecha es obligatorio.',
        'category_id' => 'El campo categoría debe ser un UUID válido.',
        'description' => 'El campo descripción no debe ser mayor que 255 caracteres.',
        'notes' => 'El campo notas no debe ser mayor que 255 caracteres.',
    ];
}

/**
 * @return array<string, string>
 */
function transferFieldErrors(): array
{
    return [
        'from_account_id' => 'El campo cuenta de origen y cuenta de destino deben ser diferentes.',
        'to_account_id' => 'El campo cuenta de destino debe ser un UUID válido.',
        'amount' => 'El campo monto es obligatorio.',
        'date' => 'El campo fecha es obligatorio.',
        'category_id' => 'El campo categoría debe ser un UUID válido.',
        'description' => 'El campo descripción no debe ser mayor que 255 caracteres.',
    ];
}

test('creating a team names its fields in Spanish', function () {
    $this->actingAs($this->owner)
        ->post(route('teams.store'), ['icon' => 'Not An Icon'])
        ->assertSessionHasErrors([
            'name' => 'El campo nombre es obligatorio.',
            'icon' => 'El formato del campo icono no es válido.',
        ]);
});

test('deleting a team names its fields in Spanish', function () {
    $this->actingAs($this->owner)
        ->delete(route('teams.destroy', $this->team), [])
        ->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
});

test('inviting a member names its fields in Spanish', function () {
    $this->actingAs($this->owner)
        ->post(route('teams.invitations.store', $this->team), ['email' => 'invited@example.com'])
        ->assertSessionHasErrors(['role' => 'El campo rol es obligatorio.']);
});

test('changing a member role names its fields in Spanish', function () {
    $member = teamUser($this->team, TeamRole::Member);

    $this->actingAs($this->owner)
        ->patch(route('teams.members.update', [$this->team, $member]), [])
        ->assertSessionHasErrors(['role' => 'El campo rol es obligatorio.']);
});

test('updating the password names its fields in Spanish', function () {
    $this->actingAs($this->owner)
        ->put(route('user-password.update'), [])
        ->assertSessionHasErrors(['current_password' => 'El campo contraseña actual es obligatorio.']);
});

test('updating the profile names its fields in Spanish', function () {
    $this->actingAs($this->owner)
        ->patch(route('profile.update'), ['email' => $this->owner->email])
        ->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
});

test('registering names its fields in Spanish', function () {
    $this->post(route('register.store'), [
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
});

test('creating a transaction names its fields in Spanish', function () {
    $this->actingAs($this->owner)
        ->post(route('transactions.store', $this->team), [
            'category_id' => 'not-a-uuid',
            'description' => $this->tooLong,
            'notes' => $this->tooLong,
        ])
        ->assertSessionHasErrors(transactionFieldErrors());
});

test('updating a transaction names its fields in Spanish', function () {
    $account = Account::factory()->for($this->team)->create();
    $transaction = recordExpense($this->team, $account, $this->owner, makeCategory($this->team, CategoryKind::Expense));

    $this->actingAs($this->owner)
        ->patch(route('transactions.update', [$this->team, $transaction]), [
            'category_id' => 'not-a-uuid',
            'description' => $this->tooLong,
            'notes' => $this->tooLong,
        ])
        ->assertSessionHasErrors(transactionFieldErrors());
});

test('creating a transfer names its fields in Spanish', function () {
    $this->actingAs($this->owner)
        ->post(route('transfers.store', $this->team), [
            'from_account_id' => 'same',
            'to_account_id' => 'same',
            'category_id' => 'not-a-uuid',
            'description' => $this->tooLong,
        ])
        ->assertSessionHasErrors(transferFieldErrors());
});

test('updating a transfer names its fields in Spanish', function () {
    $groupId = recordTransfer(
        $this->team,
        $this->owner,
        Account::factory()->for($this->team)->create(['initial_balance' => '1000.00']),
        Account::factory()->for($this->team)->create(),
    );

    $this->actingAs($this->owner)
        ->patch(route('transfers.update', [$this->team, $groupId]), [
            'from_account_id' => 'same',
            'to_account_id' => 'same',
            'category_id' => 'not-a-uuid',
            'description' => $this->tooLong,
        ])
        ->assertSessionHasErrors(transferFieldErrors());
});
