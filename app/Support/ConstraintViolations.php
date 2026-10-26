<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

final class ConstraintViolations
{
    private const array MAP = [
        'transactions_amount_positive' => ['amount', 'El monto debe ser mayor que cero.'],
        'recurring_expenses_amount_positive' => ['amount', 'El monto debe ser mayor que cero.'],
        'budgets_limit_amount_positive' => ['limit_amount', 'El límite debe ser mayor que cero.'],
        'budgets_no_overlap' => ['category_id', 'Ya existe un presupuesto para esta categoría en el periodo indicado.'],
        'teams_color_hex' => ['color', 'El color debe tener el formato #RRGGBB.'],
        'categories_kind_valid' => ['kind', 'El tipo de categoría no es válido.'],
    ];

    public static function toValidationException(QueryException $exception): ?ValidationException
    {
        if (! in_array($exception->getCode(), ['23514', '23P01'], true)) {
            return null;
        }

        $name = self::constraintName($exception);

        if ($name === null || ! array_key_exists($name, self::MAP)) {
            return null;
        }

        [$field, $message] = self::MAP[$name];

        return ValidationException::withMessages([$field => $message]);
    }

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
