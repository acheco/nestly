<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\RecurringExpense;
use App\Models\Team;
use App\Models\User;

class RecurringExpensePolicy
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
    public function view(User $user, RecurringExpense $recurringExpense): bool
    {
        return $user->belongsToTeam($recurringExpense->team);
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
    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $recurringExpense->created_by === $user->id
            || ($user->teamRole($recurringExpense->team)?->isAtLeast(TeamRole::Admin) ?? false);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->update($user, $recurringExpense);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RecurringExpense $recurringExpense): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RecurringExpense $recurringExpense): bool
    {
        return false;
    }
}
