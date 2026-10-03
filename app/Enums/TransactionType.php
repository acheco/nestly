<?php

namespace App\Enums;

use App\Support\Money;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';

    public function value(): string
    {
        return $this->value;
    }

    public function getSymbol(): string
    {
        return match ($this) {
            self::Income => '+',
            self::Expense => '-',
        };
    }

    /**
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public function signedAmount(string $amount): string
    {
        return match ($this) {
            self::Income => $amount,
            self::Expense => bcsub('0', $amount, Money::SCALE),
        };
    }
}
