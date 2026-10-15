<?php

namespace App\Actions\Transactions;

use App\DTOs\Transactions\CreateTransferData;
use App\DTOs\Transactions\TransferResultData;
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

        if ($data->date->toDateString() > $user->localToday()) {
            throw ValidationException::withMessages(['date' => 'La fecha no puede ser posterior a hoy.']); // TODO(i18n)
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

            /**
             *  Private accounts only move money across the team perimeter (team → private, private → team).
             *  Private → private never touches team money, so it is rejected. Checked after the 403 so a user
             *  moving money out of someone else's private account gets 403, not a hint about the destination.
             */
            if ($fromAccount->visibility === AccountVisibility::Private && $toAccount->visibility === AccountVisibility::Private) {
                throw ValidationException::withMessages(['to_account_id' => 'No se puede transferir entre dos cuentas privadas.']); // TODO(i18n)
            }

            if ($fromAccount->archived) {
                throw ValidationException::withMessages(['from_account_id' => 'No se puede transferir desde una cuenta archivada.']); // TODO(i18n)
            }

            if ($toAccount->archived) {
                throw ValidationException::withMessages(['to_account_id' => 'No se puede transferir a una cuenta archivada.']); // TODO(i18n)
            }

            /**
             *  Optional category (ERD §4.3.17): only when the transfer crosses the team perimeter
             *  (one team account and one private account), stored only on the team-side leg.
             *  Private → private was rejected above, so "same visibility" here can only mean team → team.
             */
            $expenseCategoryId = null;
            $incomeCategoryId = null;

            if ($data->category_id !== null) {
                $category = $team->categories()->findOrFail($data->category_id);

                if ($fromAccount->visibility === $toAccount->visibility) {
                    throw ValidationException::withMessages(['category_id' => 'Una transferencia entre cuentas del team no lleva categoría.']); // TODO(i18n)
                }

                // The team-side leg is the expense when money leaves the team, the income when it enters.
                $teamLegType = $fromAccount->visibility === AccountVisibility::Team
                    ? TransactionType::Expense
                    : TransactionType::Income;

                if ($category->kind->value !== $teamLegType->value) {
                    throw ValidationException::withMessages(['category_id' => 'El tipo de categoría no coincide con la transferencia. Selecciona una categoría válida para este registro.']); // TODO(i18n)
                }

                if ($teamLegType === TransactionType::Expense) {
                    $expenseCategoryId = $category->id;
                } else {
                    $incomeCategoryId = $category->id;
                }
            }

            $transferGroupId = Str::uuid()->toString();

            $expenseTransaction = Transaction::create([
                'team_id' => $team->id,
                'transfer_group_id' => $transferGroupId,
                'account_id' => $fromAccount->id,
                'category_id' => $expenseCategoryId,
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
                'category_id' => $incomeCategoryId,
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
