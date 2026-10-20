<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Support\TransactionDateRule;
use App\DTOs\Transactions\UpdateTransactionData;
use App\Enums\AccountVisibility;
use App\Models\Account;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Edita una transacción normal con la regla "revertir el efecto viejo +
 * aplicar el nuevo" (TRD §3.15.6). current_balance es un total acumulado,
 * así que editar una transacción vieja no afecta a las posteriores.
 */
class UpdateTransactionAction
{
    public function __construct(private readonly TransactionDateRule $dateRule) {}

    /**
     * @throws Throwable
     */
    public function execute(Team $team, User $user, string $transactionId, UpdateTransactionData $data): Transaction
    {
        $this->dateRule->assertNotInFuture($user, $data->date);

        return DB::transaction(function () use ($team, $transactionId, $data) {
            // 1. Bloquear la transacción primero: evita que dos ediciones
            //    simultáneas lean el mismo original y reviertan dos veces.
            //    Orden global de locks: transacción → cuentas por id.
            // No cubierto por test
            $transaction = $team->transactions()->lockForUpdate()->findOrFail($transactionId);

            if ($transaction->transfer_group_id !== null) {
                throw ValidationException::withMessages([
                    'account_id' => 'Esta transacción es parte de una transferencia; edítala como transferencia.', // TODO(i18n)
                ]);
            }

            $oldAccountId = $transaction->account_id;
            $oldType = $transaction->type;
            $oldAmount = $transaction->amount;

            // 2. Bloquear cuenta vieja y nueva en una sola consulta, en orden
            //    por id (mismo criterio anti-deadlock que CreateTransferAction).
            $accountIds = array_values(array_unique([$oldAccountId, $data->account_id]));

            $accounts = $team->accounts()
                ->lockForUpdate()
                ->whereIn('id', $accountIds)
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            if ($accounts->count() !== count($accountIds)) {
                throw (new ModelNotFoundException)->setModel(Account::class, $accountIds);
            }

            /** @var Account $oldAccount */
            $oldAccount = $accounts->get($oldAccountId);
            /** @var Account $newAccount */
            $newAccount = $accounts->get($data->account_id);

            // 3. Una cuenta archivada no se edita: hay que reactivarla primero.
            if ($oldAccount->archived) {
                throw ValidationException::withMessages([
                    'account_id' => 'La transacción pertenece a una cuenta archivada. Reactívala para poder editarla.', // TODO(i18n)
                ]);
            }

            if ($newAccount->archived) {
                throw ValidationException::withMessages([
                    'account_id' => 'No se pueden registrar transacciones en una cuenta archivada.', // TODO(i18n)
                ]);
            }

            if ($newAccount->visibility === AccountVisibility::Private) {
                throw ValidationException::withMessages([
                    'account_id' => 'No puedes registrar transacciones en una cuenta privada sin un grupo de transferencia.', // TODO(i18n)
                ]);
            }

            $categoryId = null;

            if ($data->category_id !== null) {
                $category = $team->categories()->findOrFail($data->category_id);

                if ($category->kind->value !== $data->type->value) {
                    throw ValidationException::withMessages([
                        'category_id' => 'El tipo de categoría no coincide con la transacción. Selecciona una categoría válida para este registro.', // TODO(i18n)
                    ]);
                }

                $categoryId = $category->id;
            }

            // 4. Revertir el efecto viejo y aplicar el nuevo. Si la cuenta no
            //    cambia, $oldAccount y $newAccount son la misma instancia.
            $oldAccount->applyTransaction($oldType->opposite(), $oldAmount);
            $newAccount->applyTransaction($data->type, $data->amount);

            $transaction->update([
                'account_id' => $newAccount->id,
                'category_id' => $categoryId,
                'type' => $data->type,
                'amount' => $data->amount,
                'date' => $data->date,
                'description' => $data->description,
                'notes' => $data->notes,
            ]);

            $accounts->each(fn (Account $account) => $account->save());

            return $transaction;
        });
    }
}
