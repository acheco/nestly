<?php

namespace App\Actions\Transactions;

use App\DTOs\CreateTransactionData;
use App\Enums\AccountVisibility;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateTransactionAction
{
    /**
     * @throws Throwable
     */
    public function execute(Team $team, User $user, CreateTransactionData $data): Transaction
    {
        return DB::transaction(function () use ($team, $user, $data) {

            $account = $team->accounts()->lockForUpdate()->findOrFail($data->account_id);

            if ($account->visibility === AccountVisibility::Private && $data->transfer_group_id === null) {
                throw ValidationException::withMessages([
                    'account_id' => 'No puedes registrar transacciones en una cuenta privada sin un grupo de transferencia.', // TODO(i18n)
                ]);
            }

            $category = null;

            if ($data->category_id !== null) {
                $category = $team->categories()->findOrFail($data->category_id);

                if ($category->kind->value !== $data->type->value) {
                    throw ValidationException::withMessages([
                        'category_id' => 'El tipo de categoría no coincide con la transacción. Selecciona una categoría válida para este registro.', // TODO(i18n)
                    ]);
                }
            }

            $recurringId = $data->recurring_id !== null
                ? $team->recurringExpenses()->findOrFail($data->recurring_id)->id
                : null;

            $transaction = Transaction::create([
                'team_id' => $team->id,
                'account_id' => $account->id,
                'category_id' => $category?->id,
                'created_by' => $user->id,
                'type' => $data->type,
                'amount' => $data->amount,
                'date' => $data->date,
                'description' => $data->description,
                'notes' => $data->notes,
                'transfer_group_id' => $data->transfer_group_id,
                'recurring_id' => $recurringId,
            ]);

            /**
             * Actualizar el balance de la cuenta
             */
            $account->applyTransaction($data->type, $data->amount)->save();

            return $transaction;
        });
    }
}
