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
                    'account_id' => __('This transaction is part of a transfer and cannot be deleted on its own.'),
                ]);
            }

            $account = $team->accounts()->lockForUpdate()->findOrFail($transaction->account_id);

            if ($account->archived) {
                throw ValidationException::withMessages([
                    'account_id' => __('A record from an archived account cannot be deleted. Reactivate the account first.'),
                ]);
            }

            $account->applyTransaction($transaction->type->opposite(), $transaction->amount)->save();

            $transaction->delete();

        });
    }
}
