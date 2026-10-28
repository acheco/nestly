<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\RecurringExpense;
use App\Models\Team;
use App\Models\User;

final class RecurringExpensePolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, RecurringExpense $recurringExpense): bool
    {
        return $user->belongsToTeam($recurringExpense->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $this->canManage($user, $team);
    }

    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canManage($user, $recurringExpense->team);
    }

    public function delete(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canManage($user, $recurringExpense->team);
    }

    public function pause(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canManage($user, $recurringExpense->team);
    }

    public function resume(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canManage($user, $recurringExpense->team);
    }

    private function canManage(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
