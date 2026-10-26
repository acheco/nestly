<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Team;
use App\Support\ConstraintViolations;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    // A QueryException shaped like PostgreSQL's, without touching the database.
    // PDOException's constructor only takes an int code, but PDO itself sets
    // SQLSTATE strings ("23514"). $code is protected, so an anonymous subclass
    // writes it; PHP forbids binding a closure to an internal class to do the same.
    $this->violation = function (string $sqlState, string $constraint): QueryException {
        $pdo = new class("SQLSTATE[{$sqlState}]: 7 ERROR:  new row violates check constraint \"{$constraint}\"", $sqlState) extends PDOException
        {
            public function __construct(string $message, string $sqlState)
            {
                parent::__construct($message);
                $this->code = $sqlState;
            }
        };

        return new QueryException('pgsql', 'update ...', [], $pdo);
    };

    // Runs a query that must fail and hands back the real exception PostgreSQL raised.
    $this->caught = function (Closure $query): QueryException {
        try {
            $query();
        } catch (QueryException $exception) {
            return $exception;
        }

        throw new RuntimeException('The query was expected to violate a constraint.');
    };
});

describe('translation', function () {
    test('a mapped constraint becomes a field error', function (string $sqlState, string $constraint, string $field) {
        $exception = ConstraintViolations::toValidationException(($this->violation)($sqlState, $constraint));

        expect($exception)->toBeInstanceOf(ValidationException::class)
            ->and($exception->errors())->toHaveKey($field);
    })->with([
        'transactions_amount_positive' => ['23514', 'transactions_amount_positive', 'amount'],
        'recurring_expenses_amount_positive' => ['23514', 'recurring_expenses_amount_positive', 'amount'],
        'budgets_limit_amount_positive' => ['23514', 'budgets_limit_amount_positive', 'limit_amount'],
        'budgets_no_overlap' => ['23P01', 'budgets_no_overlap', 'category_id'],
        'teams_color_hex' => ['23514', 'teams_color_hex', 'color'],
        'categories_kind_valid' => ['23514', 'categories_kind_valid', 'kind'],
    ]);

    test('a localized PostgreSQL message is still recognized', function () {
        // A server with lc_messages in Spanish quotes with « » and says "restricción":
        // the translation must rely on the constraint name, never on the wording.
        $pdo = new class('SQLSTATE[23514]: Check violation: 7 ERROR:  el nuevo registro para la relación «teams» viola la restricción «check» «teams_color_hex»') extends PDOException
        {
            public function __construct(string $message)
            {
                parent::__construct($message);
                $this->code = '23514';
            }
        };

        $exception = ConstraintViolations::toValidationException(new QueryException('pgsql', 'update ...', [], $pdo));

        expect($exception?->errors())->toHaveKey('color');
    });

    test('a mapped name inside user data does not count', function () {
        // The name appears only in the DETAIL line (the failing row), not in the error line.
        $pdo = new class("SQLSTATE[23514]: Check violation: 7 ERROR:  new row violates check constraint \"accounts_visibility_owner_consistent\"\nDETAIL:  Failing row contains (transactions_amount_positive).") extends PDOException
        {
            public function __construct(string $message)
            {
                parent::__construct($message);
                $this->code = '23514';
            }
        };

        expect(ConstraintViolations::toValidationException(new QueryException('pgsql', 'update ...', [], $pdo)))->toBeNull();
    });

    test('a constraint only a bug can violate is not translated', function () {
        // The action sets owner_id, never the form: a violation here is a bug and must stay a 500.
        expect(ConstraintViolations::toValidationException(($this->violation)('23514', 'accounts_visibility_owner_consistent')))
            ->toBeNull();
    });

    test('other SQLSTATEs are not translated, even with a mapped name in the message', function (string $sqlState) {
        expect(ConstraintViolations::toValidationException(($this->violation)($sqlState, 'transactions_amount_positive')))
            ->toBeNull();
    })->with([
        'unique violation' => ['23505'],
        'foreign key violation' => ['23503'],
        'invalid text representation' => ['22P02'],
    ]);
});

describe('against real PostgreSQL errors', function () {
    // These pin the regex to the message format PostgreSQL actually produces.
    test('a check violation is translated', function () {
        $team = Team::factory()->create();
        $account = Account::factory()->create(['team_id' => $team->id, 'initial_balance' => '1000.00']);
        $transaction = recordExpense($team, $account, teamUser($team, TeamRole::Member));

        $exception = ($this->caught)(fn () => DB::table('transactions')
            ->where('id', $transaction->id)
            ->update(['amount' => '0.00']));

        expect(ConstraintViolations::toValidationException($exception)?->errors())->toHaveKey('amount');
    });

    test('an exclusion violation is translated', function () {
        $team = Team::factory()->create();
        $budget = newBudget($team);

        $exception = ($this->caught)(fn () => Budget::factory()->create([
            'team_id' => $team->id,
            'category_id' => $budget->category_id,
        ]));

        expect(ConstraintViolations::toValidationException($exception)?->errors())->toHaveKey('category_id');
    });
});

describe('over HTTP', function () {
    beforeEach(function () {
        $this->team = Team::factory()->create();

        // Test-only routes: they write past the Form Requests on purpose, so the
        // database constraint is what fails and the exception handler is what answers.
        Route::middleware('web')->post('/_test/constraints/mapped', function () {
            DB::table('teams')->where('id', $this->team->id)->update(['color' => 'red']);
        });

        Route::middleware('web')->post('/_test/constraints/unmapped', function () {
            DB::table('accounts')->insert([
                'id' => (string) Str::uuid(),
                'team_id' => $this->team->id,
                'name' => 'Broken private account',
                'type' => 'savings',
                'initial_balance' => '0.00',
                'current_balance' => '0.00',
                'visibility' => 'private',
                'owner_id' => null,
                'archived' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    });

    test('an Inertia request is redirected back with the field error', function () {
        $this->from('/somewhere')
            ->post('/_test/constraints/mapped')
            ->assertRedirect('/somewhere')
            ->assertSessionHasErrors('color');
    });

    test('a JSON request gets a 422 with the field error', function () {
        $this->postJson('/_test/constraints/mapped')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('color');
    });

    test('an unmapped constraint stays a 500', function () {
        $this->postJson('/_test/constraints/unmapped')
            ->assertInternalServerError();
    });
});
