<?php

namespace App\Policies;

use App\Enums\AccountVisibility;
use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function view(User $user, Account $account): bool
    {
        if ($account->visibility == AccountVisibility::Team) {
            return $user->belongsToTeam($account->team);
        }

        // Private: Solo el dueño o admin/owner del team
        return $account->owner_id === $user->id
        ||
        $user->teamRole($account->team)?->isAtLeast(TeamRole::Admin);
    }

    public function create(User $user, Team $team): bool
    {
        return $user->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    public function archive(User $user, Account $account): bool
    {
        return $this->update($user, $account);
    }

    public function update(User $user, Account $account): bool
    {
        return $user->teamRole($account->team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->teamRole($account->team)?->isAtLeast(TeamRole::Admin) ?? false;
    }
}
