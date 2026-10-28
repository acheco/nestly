<?php

namespace App\Policies;

use App\Enums\TeamRole;
use App\Models\Category;
use App\Models\Team;
use App\Models\User;

final class CategoryPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->belongsToTeam($category->team);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    public function update(User $user, Category $category): bool
    {
        if ($category->is_system) {
            return false;
        }

        return $user->teamRole($category->team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    public function archive(User $user, Category $category): bool
    {
        return $user->teamRole($category->team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    public function delete(User $user, Category $category): bool
    {
        if ($category->is_system) {
            return false;
        }

        return $user->teamRole($category->team)?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
