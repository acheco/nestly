<?php

namespace App\Actions\Users;

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\RecurringExpense;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Temporary mitigation for D-060 and D-076: until users are anonymized, deleting one is refused
 * when it would fail on a foreign key, leave a shared team without an owner, or erase the author
 * of their transactions.
 */
final class DeleteUserAction
{
    /**
     * @throws Throwable
     */
    public function execute(User $user): void
    {
        DB::transaction(function () use ($user) {
            // This lock makes a concurrent insert that references the user wait for the commit,
            // so nothing can appear between the checks and the delete. No test covers it.
            // Deleting this copy also matters: logging out saves the guard's instance, and
            // saving an instance that was itself deleted would insert the user again.
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $reasons = $this->blockingReasons($locked);

            if ($reasons !== []) {
                throw ValidationException::withMessages([
                    'account' => 'No puedes eliminar tu cuenta todavía: '.implode('; ', $reasons).'.', // TODO(i18n)
                ]);
            }

            $locked->delete();
        });
    }

    /**
     * @return list<string>
     */
    private function blockingReasons(User $user): array
    {
        $reasons = [];

        // Not scoped to a team or to active rows: the foreign keys don't distinguish them either.
        if (Account::where('owner_id', $user->id)->exists()) {
            $reasons[] = 'tienes cuentas privadas'; // TODO(i18n)
        }

        if (RecurringExpense::where('created_by', $user->id)->exists()) {
            $reasons[] = 'creaste gastos recurrentes'; // TODO(i18n)
        }

        if (Transaction::where('created_by', $user->id)->exists()) {
            $reasons[] = 'registraste movimientos'; // TODO(i18n)
        }

        if ($this->isOnlyOwnerOfSharedTeam($user)) {
            $reasons[] = 'eres el único owner de un team compartido'; // TODO(i18n)
        }

        return $reasons;
    }

    private function isOnlyOwnerOfSharedTeam(User $user): bool
    {
        return Team::query()
            ->where('is_personal', false)
            ->whereHas('memberships', fn (Builder $query) => $query
                ->where('user_id', $user->id)
                ->where('role', TeamRole::Owner->value))
            ->whereDoesntHave('memberships', fn (Builder $query) => $query
                ->where('user_id', '!=', $user->id)
                ->where('role', TeamRole::Owner->value))
            ->exists();
    }
}
