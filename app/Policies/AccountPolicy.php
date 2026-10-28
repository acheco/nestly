<?php

namespace App\Policies;

use App\Enums\AccountVisibility;
use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\Team;
use App\Models\User;

final class AccountPolicy
{
    public function viewAny(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /** Name level: every member, viewers included, sees every account's name (PRD §1.6.3). */
    public function view(User $user, Account $account): bool
    {
        return $user->belongsToTeam($account->team);
    }

    /** Balance level: a private account's balance is visible to its owner only, not even to the team owner. */
    public function viewBalance(User $user, Account $account): bool
    {
        if ($account->visibility === AccountVisibility::Private) {
            // Owner only, and only while still in the team: once they leave, nobody sees it (PRD §1.6.3).
            return $account->owner_id === $user->id && $user->belongsToTeam($account->team);
        }

        return $user->belongsToTeam($account->team);
    }

    public function create(User $user, Team $team, AccountVisibility $visibility): bool
    {
        $role = $user->teamRole($team);

        if ($role === null) {
            return false;
        }

        return match ($visibility) {
            AccountVisibility::Team => $role->isAtLeast(TeamRole::Admin),
            AccountVisibility::Private => $role->isAtLeast(TeamRole::Member),
        };
    }

    public function archive(User $user, Account $account): bool
    {
        return $this->canManage($user, $account);
    }

    public function update(User $user, Account $account): bool
    {
        return $this->canManage($user, $account);
    }

    /** Role/ownership only; "no transactions" is a 422 decided in the action (ERD §4.3.18). */
    public function delete(User $user, Account $account): bool
    {
        return $this->canManage($user, $account);
    }

    private function canManage(User $user, Account $account): bool
    {
        $role = $user->teamRole($account->team);

        if ($role === null) {
            return false;
        }

        if ($account->visibility === AccountVisibility::Team) {
            return $role->isAtLeast(TeamRole::Admin);
        }

        // Private: only its owner, and only while still at least a member of the team.
        return $account->owner_id === $user->id && $role->isAtLeast(TeamRole::Member);
    }
}
