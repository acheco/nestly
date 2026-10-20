<?php

namespace App\Policies;

use App\Enums\TeamRole;
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
    public function create(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Member) ?? false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        $role = $user->teamRole($transaction->team);

        if ($role === null || ! $role->isAtLeast(TeamRole::Member)) {
            return false;
        }

        return $transaction->created_by === $user->id || $role->isAtLeast(TeamRole::Admin);
    }

    /**
     * Role-only gate for updating a transfer (no Transfer model to bind).
     * Creator / private-account owner / admin+ is checked in UpdateTransferAction
     * against the locked rows.
     */
    public function updateTransfer(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Member) ?? false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }

    /**
     * Role-only gate for deleting a transfer (no Transfer model to bind).
     * Creator / private-account owner / admin+ is checked in DeleteTransferAction
     * against the locked rows.
     */
    public function deleteTransfer(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Member) ?? false;
    }
}
