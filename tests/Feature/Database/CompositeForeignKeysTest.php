<?php

use App\Enums\CategoryKind;
use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Budget;
use App\Models\RecurringExpense;
use App\Models\Team;
use App\Support\ConstraintViolations;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * D-074: PostgreSQL itself refuses a reference to another team, even if an action forgets its team filter.
 */

beforeEach(function () {
    $this->team = Team::factory()->create();
    $this->admin = teamUser($this->team, TeamRole::Admin);
    $this->account = Account::factory()->create(['team_id' => $this->team->id]);
    $this->category = makeCategory($this->team, CategoryKind::Expense);

    $this->otherTeam = Team::factory()->create();
    $this->otherAccount = Account::factory()->create(['team_id' => $this->otherTeam->id]);
    $this->otherCategory = makeCategory($this->otherTeam, CategoryKind::Expense);

    $this->insertTransaction = function (array $overrides = []): string {
        $id = (string) Str::uuid();

        DB::table('transactions')->insert(array_merge([
            'id' => $id,
            'team_id' => $this->team->id,
            'account_id' => $this->account->id,
            'category_id' => null,
            'recurring_id' => null,
            'type' => 'expense',
            'amount' => '1.00',
            'date' => '2026-09-15',
        ], $overrides));

        return $id;
    };

    // The savepoint keeps the test's own transaction usable after the expected failure.
    $this->violation = function (Closure $write): QueryException {
        try {
            DB::transaction($write);
        } catch (QueryException $exception) {
            return $exception;
        }

        throw new RuntimeException('The database accepted the write.');
    };
});

test('a reference to another team is rejected', function (string $constraint) {
    $write = match ($constraint) {
        'transactions_account_id_team_foreign' => fn () => ($this->insertTransaction)(['account_id' => $this->otherAccount->id]),
        'transactions_category_id_team_foreign' => fn () => ($this->insertTransaction)(['category_id' => $this->otherCategory->id]),
        'transactions_recurring_id_team_foreign' => fn () => ($this->insertTransaction)([
            'recurring_id' => newRecurringExpense($this->otherTeam, $this->otherAccount, teamUser($this->otherTeam, TeamRole::Admin))->id,
        ]),
        'recurring_expenses_account_id_team_foreign' => fn () => RecurringExpense::factory()->create([
            'team_id' => $this->team->id,
            'account_id' => $this->otherAccount->id,
            'category_id' => $this->category->id,
            'created_by' => $this->admin->id,
        ]),
        'recurring_expenses_category_id_team_foreign' => fn () => RecurringExpense::factory()->create([
            'team_id' => $this->team->id,
            'account_id' => $this->account->id,
            'category_id' => $this->otherCategory->id,
            'created_by' => $this->admin->id,
        ]),
        'budgets_category_id_team_foreign' => fn () => Budget::factory()->create([
            'team_id' => $this->team->id,
            'category_id' => $this->otherCategory->id,
        ]),
        'categories_parent_id_team_foreign' => fn () => DB::table('categories')
            ->where('id', $this->category->id)
            ->update(['parent_id' => $this->otherCategory->id]),
    };

    $exception = ($this->violation)($write);

    // Only a bug can reach this, so it stays a 500 instead of becoming a form error.
    expect($exception->getCode())->toBe('23503')
        ->and($exception->getMessage())->toContain($constraint)
        ->and(ConstraintViolations::toValidationException($exception))->toBeNull();
})->with([
    'transactions_account_id_team_foreign',
    'transactions_category_id_team_foreign',
    'transactions_recurring_id_team_foreign',
    'recurring_expenses_account_id_team_foreign',
    'recurring_expenses_category_id_team_foreign',
    'budgets_category_id_team_foreign',
    'categories_parent_id_team_foreign',
]);

test('a reference inside the same team is accepted', function () {
    $recurring = newRecurringExpense($this->team, $this->account, $this->admin);

    $id = ($this->insertTransaction)([
        'category_id' => $this->category->id,
        'recurring_id' => $recurring->id,
    ]);

    $this->assertDatabaseHas('transactions', ['id' => $id, 'category_id' => $this->category->id]);
});

test('a transaction without a category is still accepted', function () {
    $id = ($this->insertTransaction)(['category_id' => null]);

    $this->assertDatabaseHas('transactions', ['id' => $id, 'category_id' => null]);
});

test('a category without a parent is still accepted', function () {
    expect($this->category->fresh()->parent_id)->toBeNull();
});

test('deleting a recurring expense keeps the transaction and its team', function () {
    $recurring = newRecurringExpense($this->team, $this->account, $this->admin);
    $id = ($this->insertTransaction)(['recurring_id' => $recurring->id]);

    DB::table('recurring_expenses')->where('id', $recurring->id)->delete();

    $this->assertDatabaseHas('transactions', [
        'id' => $id,
        'recurring_id' => null,
        'team_id' => $this->team->id,
    ]);
});

test('deleting a row held by a reference stays restricted', function (string $table) {
    ($this->insertTransaction)(['category_id' => $this->category->id]);

    $id = $table === 'accounts' ? $this->account->id : $this->category->id;

    $exception = ($this->violation)(fn () => DB::table($table)->where('id', $id)->delete());

    expect($exception->getCode())->toBe('23001');
})->with(['accounts', 'categories']);

describe('the migration', function () {
    beforeEach(function () {
        $this->migration = require database_path('migrations/2026_11_03_120000_add_composite_team_foreign_keys.php');
    });

    test('aborts and changes nothing when a row already crosses teams', function () {
        $this->migration->down();

        $id = ($this->insertTransaction)(['account_id' => $this->otherAccount->id]);

        expect(fn () => $this->migration->up())->toThrow(RuntimeException::class, 'transactions.account_id');

        // Still the simple foreign keys: the row is there and another cross-team one is accepted.
        $this->assertDatabaseHas('transactions', ['id' => $id]);
        ($this->insertTransaction)(['category_id' => $this->otherCategory->id]);
    });

    test('can be reverted and applied again', function () {
        $this->migration->down();

        ($this->insertTransaction)();

        $this->migration->up();

        $exception = ($this->violation)(fn () => ($this->insertTransaction)(['account_id' => $this->otherAccount->id]));

        expect($exception->getCode())->toBe('23503');
    });
});
