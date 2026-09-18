<?php

namespace App\Enums;

enum AccountVisibility: string
{
    case Private = 'private';
    case Team = 'team';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Private',
            self::Team => 'Team',
        };
    }
}
