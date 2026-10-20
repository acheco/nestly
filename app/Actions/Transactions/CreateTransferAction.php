<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Support\TransferRules;
use App\DTOs\Transactions\CreateTransferData;
use App\DTOs\Transactions\TransferResultData;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

readonly class CreateTransferAction
{
    public function __construct(private TransferRules $rules) {}

    /**
     * @throws Throwable
     */
    public function execute(Team $team, User $user, CreateTransferData $data): TransferResultData
    {

        $this->rules->assertValidInput($user, $data->from_account_id, $data->to_account_id, $data->date);

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

            $this->rules->assertAccounts($user, $fromAccount, $toAccount);

            $categoryIds = $this->rules->resolveCategoryIds($team, $fromAccount, $toAccount, $data->category_id);

            $transferGroupId = Str::uuid()->toString();

            $expenseTransaction = Transaction::create([
                'team_id' => $team->id,
                'transfer_group_id' => $transferGroupId,
                'account_id' => $fromAccount->id,
                'category_id' => $categoryIds['expense'],
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
                'category_id' => $categoryIds['income'],
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
