<?php

namespace App\Actions\Transactions;

use App\Enums\TeamRole;
use App\Enums\TransactionType;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeleteTransferAction
{
    /**
     * The member+ role check lives in TransactionPolicy::deleteTransfer (no DB needed).
     * The creator / private-account owner / admin+ check lives here because it needs
     * the locked rows: checking before the lock would authorize against stale data.
     *
     * @throws Throwable
     */
    public function execute(Team $team, User $user, string $transferGroupId): void
    {
        DB::transaction(function () use ($team, $user, $transferGroupId) {
            // Global lock order: transactions first, then accounts (by id) — same as update/delete.
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

            $accounts = $team->accounts()
                ->lockForUpdate()
                ->whereIn('id', [$expenseLeg->account_id, $incomeLeg->account_id])
                ->orderBy('id')
                ->get()
                ->keyBy('id');

            $fromAccount = $accounts->get($expenseLeg->account_id);
            $toAccount = $accounts->get($incomeLeg->account_id);

            // Case A/B: creator, owner of an involved private account, or admin+.
            $isCreator = $expenseLeg->created_by === $user->id;
            $ownsInvolvedAccount = $fromAccount->owner_id === $user->id || $toAccount->owner_id === $user->id;
            $isAdmin = $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;

            if (! $isCreator && ! $ownsInvolvedAccount && ! $isAdmin) {
                throw new AuthorizationException('No tienes permiso para eliminar esta transferencia.'); // TODO(i18n)
            }

            if ($fromAccount->archived) {
                throw ValidationException::withMessages([
                    'account_id' => 'No se puede borrar una transferencia de una cuenta origen archivada, primero active la cuenta.', // TODO(i18n)
                ]);
            }

            if ($toAccount->archived) {
                throw ValidationException::withMessages([
                    'account_id' => 'No se puede borrar una transferencia hacia una cuenta destino archivada, primero active la cuenta.', // TODO(i18n)
                ]);
            }

            $fromAccount->applyTransaction($expenseLeg->type->opposite(), $expenseLeg->amount)->save();
            $toAccount->applyTransaction($incomeLeg->type->opposite(), $incomeLeg->amount)->save();

            $legs->each->delete();
        });
    }
}
