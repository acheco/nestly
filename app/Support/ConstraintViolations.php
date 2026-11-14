<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class ConstraintViolations
{
    /**
     * Constraint name => the field that receives the error. Its message comes from message().
     */
    private const array MAP = [
        'transactions_amount_positive' => 'amount',
        'recurring_expenses_amount_positive' => 'amount',
        'budgets_limit_amount_positive' => 'limit_amount',
        'budgets_no_overlap' => 'category_id',
        'teams_color_hex' => 'color',
        'categories_kind_valid' => 'kind',
    ];

    public static function toValidationException(QueryException $exception): ?ValidationException
    {
        if (! in_array($exception->getCode(), ['23514', '23P01'], true)) {
            return null;
        }

        $name = self::constraintName($exception);

        if ($name === null) {
            return null;
        }

        return ValidationException::withMessages([self::MAP[$name] => self::message($name)]);
    }

    /**
     * One literal __() per constraint, so the translation-key guard can check them. No default arm:
     * a constraint added to MAP without its message fails PHPStan (match.unhandled).
     *
     * @param  key-of<self::MAP>  $constraint
     */
    private static function message(string $constraint): string
    {
        return match ($constraint) {
            'transactions_amount_positive', 'recurring_expenses_amount_positive' => __('The amount must be greater than zero.'),
            'budgets_limit_amount_positive' => __('The limit must be greater than zero.'),
            'budgets_no_overlap' => __('A budget already exists for this category in that period.'),
            'teams_color_hex' => __('The color must use the #RRGGBB format.'),
            'categories_kind_valid' => __('The category type is not valid.'),
        };
    }

    /**
     * @return key-of<self::MAP>|null
     */
    private static function constraintName(QueryException $exception): ?string
    {
        // PostgreSQL localizes its messages (a Spanish server says «restricción» «teams_color_hex»),
        // so instead of parsing them, look for a known constraint name. Identifiers are never translated.
        // Only the first line: later lines (DETAIL, the SQL and its bindings) can carry user data.
        $firstLine = strtok($exception->getPrevious()?->getMessage() ?? $exception->getMessage(), "\n");

        foreach (array_keys(self::MAP) as $name) {
            if (str_contains((string) $firstLine, $name)) {
                return $name;
            }
        }

        return null;
    }
}
