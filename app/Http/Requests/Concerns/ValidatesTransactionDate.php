<?php

namespace App\Http\Requests\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;

/**
 * Regla compartida de la fecha de un movimiento (transacción o transferencia).
 *
 * No se permiten fechas futuras: un registro a futuro no refleja la cuenta
 * real y puede no ocurrir nunca. "Hoy" es el día en la zona horaria del
 * usuario, no el del servidor (UTC). Ver TRD §3.6.
 */
trait ValidatesTransactionDate
{
    /**
     * @return list<string>
     *
     * @throws AuthenticationException
     */
    protected function transactionDateRules(): array
    {
        $user = $this->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return ['required', 'date', 'before_or_equal:'.$user->localToday()];
    }
}
