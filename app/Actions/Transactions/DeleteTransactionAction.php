<?php

namespace App\Actions\Transactions;

use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeleteTransactionAction
{
    /**
     * @throws Throwable
     */
    public function execute(Team $team, string $transactionId): void
    {
        DB::transaction(function () use ($team, $transactionId) {
            $transaction = $team->transactions()->lockForUpdate()->findOrFail($transactionId);

            if ($transaction->transfer_group_id !== null) {
                throw ValidationException::withMessages([
                    'account_id' => 'Esta transacción es parte de una transferencia y no puede eliminarse por separado.', // TODO(i18n)
                ]);
            }

            $account = $team->accounts()->lockForUpdate()->findOrFail($transaction->account_id);

            if ($account->archived) {
                throw ValidationException::withMessages([
                    'account_id' => 'No se puede borrar un registro de una cuenta archivada, primero active la cuenta', // TODO(i18n)
                ]);
            }

            $account->applyTransaction($transaction->type->opposite(), $transaction->amount)->save();

            $transaction->delete();

        });
    }
}
