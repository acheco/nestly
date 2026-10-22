<?php

namespace App\Enums;

enum BrokenTransferReason: string
{
    case LegCount = 'leg_count';
    case SameType = 'same_type';
    case AmountMismatch = 'amount_mismatch';
    case DateMismatch = 'date_mismatch';
    case PrivateToPrivate = 'private_to_private';
}
