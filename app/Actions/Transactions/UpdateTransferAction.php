<?php

namespace App\Actions\Transactions;

use App\Actions\Transactions\Support\TransferRules;
use App\DTOs\Transactions\UpdateTransferData;
use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

readonly class UpdateTransferAction
{
    public function __construct(private TransferRules $rules) {}

    /**
     * @throws Throwable
     */
    public function execute(Team $team, User $user, string $transferGroupId, UpdateTransferData $data): void
    {
        $this->rules->assertValidInput($user, $data->from_account_id, $data->to_account_id, $data->date);

        DB::transaction(function () use ($user, $data, $team, $transferGroupId) {
            $legs = $team->transactions()
                ->lockForUpdate()
                ->where('transfer_group_id', $transferGroupId)
                ->orderBy('id')
                ->get();

            if ($legs->count() !== 2) {
                throw (new ModelNotFoundException)->setModel(Transaction::class, [$transferGroupId]);
            }

            /** @var Transaction $expenseLeg */
            $expenseLeg = $legs->firstWhere('type', TransactionType::Expense);
            /** @var Transaction $incomeLeg */
            $incomeLeg = $legs->firstWhere('type', TransactionType::Income);

            $accountIds = array_values(array_unique([
                $expenseLeg->account_id,
                $incomeLeg->account_id,
                $data->from_account_id,
                $data->to_account_id,
            ]));

            $accounts = $team->accounts()
                ->lockForUpdate()
                ->whereIn('id', $accountIds)
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            if ($accounts->count() !== count($accountIds)) {
                throw (new ModelNotFoundException)->setModel(Account::class, $accountIds);
            }

            $oldFromAccount = $accounts->get($expenseLeg->account_id);
            $oldToAccount = $accounts->get($incomeLeg->account_id);
            $newFromAccount = $accounts->get($data->from_account_id);
            $newToAccount = $accounts->get($data->to_account_id);

            $isCreator = $expenseLeg->created_by === $user->id;
            $ownInvolvedAccount = $oldFromAccount->owner_id === $user->id || $oldToAccount->owner_id === $user->id;
            $isAdmin = $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;

            if (! $isCreator && ! $ownInvolvedAccount && ! $isAdmin) {
                throw new AuthorizationException('No tienes permiso para editar esta transferencia'); // TODO(i18n)
            }

            if ($oldFromAccount->archived) {
                throw ValidationException::withMessages([
                    'from_account_id' => 'La cuenta de origen está archivada, primero active la cuenta', // TODO(i18n)
                ]);
            }

            if ($oldToAccount->archived) {
                throw ValidationException::withMessages(['to_account_id' => 'La cuenta destino está archivada, primero active la cuenta']); // TODO(i18n)
            }

            $this->rules->assertAccounts($user, $newFromAccount, $newToAccount);
            $categoryIds = $this->rules->resolveCategoryIds($team, $newFromAccount, $newToAccount, $data->category_id);

            $oldFromAccount->applyTransaction($expenseLeg->type->opposite(), $expenseLeg->amount);
            $oldToAccount->applyTransaction($incomeLeg->type->opposite(), $incomeLeg->amount);
            $newFromAccount->applyTransaction(TransactionType::Expense, $data->amount);
            $newToAccount->applyTransaction(TransactionType::Income, $data->amount);

            $expenseLeg->fill([
                'account_id' => $newFromAccount->id,
                'category_id' => $categoryIds['expense'],
                'amount' => $data->amount,
                'description' => $data->description,
                'date' => $data->date,
            ])->save();

            $incomeLeg->fill([
                'account_id' => $newToAccount->id,
                'category_id' => $categoryIds['income'],
                'amount' => $data->amount,
                'description' => $data->description,
                'date' => $data->date,
            ])->save();

            $accounts->each->save();
        });
    }
}
