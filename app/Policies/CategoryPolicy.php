<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Category;
use App\Models\Team;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->BelongsToTeam($team);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->BelongsToTeam($category->team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Category $category): bool
    {
        if ($category->is_system) {
            return false;
        }

        return $user->teamRole($category->team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Category $category): bool
    {
        return $user->teamRole($category->team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        if ($category->is_system) {
            return false;
        }

        return $user->teamRole($category->team)?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
