<?php

namespace App\Policies;

use App\Enums\BudgetScope;
use App\Enums\TeamRole;
use App\Models\Budget;
use App\Models\Team;
use App\Models\User;

class BudgetPolicy
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
    public function view(User $user, Budget $budget): bool
    {
        return $user->belongsToTeam($budget->team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Team $team, BudgetScope $scope): bool
    {
        $minRole = $scope === BudgetScope::Personal ? TeamRole::Member : TeamRole::Admin;

        return $user->teamRole($team)?->isAtLeast($minRole) ?? false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Budget $budget): bool
    {
        if ($budget->scope == BudgetScope::Personal) {
            return $budget->owner_id === $user->id || ($user->teamRole($budget->team)?->isAtLeast(TeamRole::Admin) ?? false);
        }

        return $user->teamRole($budget->team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Budget $budget): bool
    {
        return $this->update($user, $budget);
    }
}
