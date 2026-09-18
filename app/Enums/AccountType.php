<?php

namespace App\Enums;

enum AccountType: string
{
    case Checking = 'checking';
    case Savings = 'savings';
    case Cash = 'cash';
    case Credit = 'credit';
    case Investment = 'investment';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Checking',
            self::Savings => 'Savings',
            self::Cash => 'Cash',
            self::Credit => 'Credit',
            self::Investment => 'Investment',
        };
    }
}
