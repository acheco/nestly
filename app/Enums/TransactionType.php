<?php

namespace App\Enums;

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

    public function signedAmount(string $amount): string
    {
        return match ($this) {
            self::Income => $amount,
            self::Expense => bcsub('0', $amount, 2),
        };
    }
}
