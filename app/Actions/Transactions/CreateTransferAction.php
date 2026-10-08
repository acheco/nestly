<?php

namespace App\Actions\Transactions;

use App\DTOs\CreateTransferData;
use App\DTOs\TransferResultData;
use App\Enums\AccountVisibility;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateTransferAction
{
    /**
     * @throws Throwable
     */
    public function execute(Team $team, User $user, CreateTransferData $data): TransferResultData
    {
        if ($data->from_account_id === $data->to_account_id) {
            throw ValidationException::withMessages(['from_account_id' => 'Las cuentas deben ser diferentes']); // TODO(i18n)
        }

        return DB::transaction(function () use ($team, $user, $data) {

            /**
             *  Lock both accounts in a global, deterministic order (by id) to avoid deadlocks
             *  when two transfers run concurrently in opposite directions (A→B and B→A).
             *  Not covered by tests: removing this keeps the suite green but breaks under load.
             */
            $accounts = $team->accounts()
                ->lockForUpdate()
                ->whereIn('id', [$data->from_account_id, $data->to_account_id])
                ->orderBy('id')
                ->get();

            if ($accounts->count() !== 2) {
                throw (new ModelNotFoundException)->setModel(Account::class, [
                    $data->from_account_id,
                    $data->to_account_id,
                ]);
            }

            $byId = $accounts->keyBy('id');
            $fromAccount = $byId->get($data->from_account_id);
            $toAccount = $byId->get($data->to_account_id);

            if ($fromAccount->visibility === AccountVisibility::Private && $user->id !== $fromAccount->owner_id) {
                throw new AuthorizationException('Solo el dueño puede transferir desde una cuenta privada.'); // TODO(i18n)
            }

            if ($fromAccount->archived) {
                throw ValidationException::withMessages(['from_account_id' => 'No se puede transferir desde una cuenta archivada.']); // TODO(i18n)
            }

            if ($toAccount->archived) {
                throw ValidationException::withMessages(['to_account_id' => 'No se puede transferir a una cuenta archivada.']); // TODO(i18n)
            }

            $transferGroupId = Str::uuid()->toString();

            $expenseTransaction = Transaction::create([
                'team_id' => $team->id,
                'transfer_group_id' => $transferGroupId,
                'account_id' => $fromAccount->id,
                'amount' => $data->amount,
                'description' => $data->description,
                'date' => $data->date,
                'type' => TransactionType::Expense,
                'created_by' => $user->id,
            ]);

            $incomeTransaction = Transaction::create([
                'team_id' => $team->id,
                'transfer_group_id' => $transferGroupId,
                'account_id' => $toAccount->id,
                'amount' => $data->amount,
                'description' => $data->description,
                'date' => $data->date,
                'type' => TransactionType::Income,
                'created_by' => $user->id,
            ]);

            $fromAccount->applyTransaction(TransactionType::Expense, $data->amount)->save();
            $toAccount->applyTransaction(TransactionType::Income, $data->amount)->save();

            return new TransferResultData($transferGroupId, $expenseTransaction, $incomeTransaction);
        });

    }
}
