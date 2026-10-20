<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    /**
     * Escala por defecto para las operaciones de dinero.
     */
    public const int SCALE = 2;

    /**
     * Tope de las columnas decimal(14,2): 12 enteros y 2 decimales.
     */
    public const string MAX = '999999999999.99';

    /**
     * Convierte un monto ya validado por un Form Request en numeric-string.
     *
     * @return numeric-string
     */
    public static function fromInput(mixed $value): string
    {
        $amount = (string) $value;

        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("Invalid amount: {$amount}");
        }

        return $amount;
    }
}
