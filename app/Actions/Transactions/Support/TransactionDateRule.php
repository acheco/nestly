<?php

namespace App\Actions\Transactions\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

/**
 * Domain-layer twin of ValidatesTransactionDate (Form Request layer, TRD §3.6).
 * Lives in the actions too because the seeder, the recurring job and the
 * future API call them without going through a Form Request.
 */
final class TransactionDateRule
{
    /**
     * "Today" is the user's local day, compared by date (Y-m-d), not by instant.
     */
    public function assertNotInFuture(User $user, CarbonInterface $date): void
    {
        if ($date->toDateString() > $user->localToday()) {
            throw ValidationException::withMessages([
                'date' => 'La fecha no puede ser posterior a hoy.', // TODO(i18n)
            ]);
        }
    }
}
