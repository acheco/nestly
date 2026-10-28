<?php

use App\Actions\Transactions\CreateTransactionAction;
use App\Actions\Transactions\CreateTransferAction;
use App\DTOs\Transactions\CreateTransactionData;
use App\DTOs\Transactions\CreateTransferData;
use App\DTOs\Transactions\UpdateTransactionData;
use App\DTOs\Transactions\UpdateTransferData;
use App\Enums\CategoryKind;
use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\RecurringExpense;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function transactionData(array $overrides = []): CreateTransactionData
{
    return new CreateTransactionData(...array_merge([
        'account_id' => null,
        'category_id' => null,
        'type' => TransactionType::Expense,
        'amount' => '100.00',
        'date' => CarbonImmutable::parse('2026-09-15'),
        'description' => null,
        'notes' => null,
        'transfer_group_id' => null,
        'recurring_id' => null,
    ], $overrides));
}

function transferData(array $overrides = []): CreateTransferData
{
    return new CreateTransferData(...array_merge([
        'from_account_id' => null,
        'to_account_id' => null,
        'amount' => '100.00',
        'category_id' => null,
        'description' => null,
        'date' => CarbonImmutable::parse('2026-09-15'),
    ], $overrides));
}

function updateTransactionData(array $overrides = []): UpdateTransactionData
{
    return new UpdateTransactionData(...array_merge([
        'account_id' => null,
        'category_id' => null,
        'type' => TransactionType::Expense,
        'amount' => '100.00',
        'date' => CarbonImmutable::parse('2026-09-15'),
        'description' => null,
        'notes' => null,
    ], $overrides));
}

function updateTransferData(array $overrides = []): UpdateTransferData
{
    return new UpdateTransferData(...array_merge([
        'from_account_id' => null,
        'to_account_id' => null,
        'amount' => '100.00',
        'category_id' => null,
        'description' => null,
        'date' => CarbonImmutable::parse('2026-09-15'),
    ], $overrides));
}

function makeCategory(Team $team, CategoryKind $kind): Category
{
    return Category::factory()->create([
        'team_id' => $team->id,
        'name' => 'Categoría de prueba',
        'icon' => 'tag',
        'color' => '#000000',
        'kind' => $kind,
    ]);
}

/**
 * Registra un gasto de 100.00 vía la action real, para que el saldo de la
 * cuenta quede coherente (initial_balance − 100).
 */
function recordExpense(Team $team, Account $account, User $creator, ?Category $category = null): Transaction
{
    return app(CreateTransactionAction::class)->execute($team, $creator, transactionData([
        'account_id' => $account->id,
        'category_id' => $category?->id,
        'amount' => '100.00',
    ]));
}

/**
 * Crea una transferencia de 100.00 vía la action real y devuelve su transfer_group_id.
 */
function recordTransfer(Team $team, User $creator, Account $from, Account $to): string
{
    return app(CreateTransferAction::class)->execute($team, $creator, transferData([
        'from_account_id' => $from->id,
        'to_account_id' => $to->id,
    ]))->transfer_group_id;
}

function teamUser(Team $team, TeamRole $role): User
{
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => $role->value]);

    return $user;
}

function transferLegs(string $groupId): array
{
    $legs = Transaction::where('transfer_group_id', $groupId)->get();

    return [
        'expense' => $legs->firstWhere('type', TransactionType::Expense),
        'income' => $legs->firstWhere('type', TransactionType::Income),
    ];
}

/**
 * Pisa el saldo directamente en la tabla, por detrás de las actions.
 */
function driftBalance(Account $account, string $balance): void
{
    DB::table('accounts')
        ->where('id', $account->id)
        ->update(['current_balance' => $balance]);
}

function newRecurringExpense(Team $team, Account $account, User $creator): RecurringExpense
{
    return RecurringExpense::factory()->create([
        'team_id' => $team->id,
        'account_id' => $account->id,
        'category_id' => makeCategory($team, CategoryKind::Expense)->id,
        'created_by' => $creator->id,
    ]);
}

function newBudget(Team $team, ?User $creator = null): Budget
{
    // A fresh category per budget, so budgets_no_overlap never fires between them.
    return Budget::factory()->create([
        'team_id' => $team->id,
        'category_id' => makeCategory($team, CategoryKind::Expense)->id,
        'created_by' => $creator?->id,
    ]);
}
