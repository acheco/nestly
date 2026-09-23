<?php

namespace App\Enums;

enum BudgetPeriod: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
}
