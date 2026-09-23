<?php

namespace App\Enums;

enum RecurringFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Custom = 'custom';
}
