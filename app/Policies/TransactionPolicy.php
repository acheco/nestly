<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        return (new AccountPolicy)->view($user, $transaction->account);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Account $account): bool
    {
        $hasRole = $user->teamRole($account->team)?->isAtLeast(TeamRole::Member) ?? false;

        return $hasRole && (new AccountPolicy)->view($user, $account);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        return $transaction->created_by === $user->id
            || ($user->teamRole($transaction->account->team)?->isAtLeast(TeamRole::Admin) ?? false);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
