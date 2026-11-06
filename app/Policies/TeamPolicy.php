<?php

namespace App\Policies;

use App\Enums\TeamPermission;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::UpdateTeam);
    }

    /**
     * Determine whether the user can leave the team.
     */
    public function leave(User $user, Team $team): bool
    {
        return ! $team->is_personal
            && $user->belongsToTeam($team)
            && ! $user->ownsTeam($team);
    }

    /**
     * Determine whether the user can add a member to the team.
     */
    public function addMember(User $user, Team $team): bool
    {
        return $user->hasTeamPermission($team, TeamPermission::AddMember);
    }

    /**
     * Determine whether the user can give the member the new role (D-077). Nobody changes the owner (D-089).
     */
    public function updateMember(User $user, Team $team, User $member, TeamRole $newRole): bool
    {
        $role = $user->teamRole($team);
        $current = $member->teamRole($team);

        return $role !== null && $current !== null
            && $role->hasPermission(TeamPermission::UpdateMember)
            && $role->canManage($current)
            && $role->canManage($newRole);
    }

    /**
     * Determine whether the user can remove the member from the team (D-077). Nobody removes the owner.
     */
    public function removeMember(User $user, Team $team, User $member): bool
    {
        $role = $user->teamRole($team);
        $current = $member->teamRole($team);

        return $role !== null && $current !== null
            && $role->hasPermission(TeamPermission::RemoveMember)
            && $role->canManage($current);
    }

    /**
     * Determine whether the user can invite someone with the given role (D-077).
     */
    public function inviteMember(User $user, Team $team, TeamRole $invitedRole): bool
    {
        $role = $user->teamRole($team);

        return $role !== null
            && $role->hasPermission(TeamPermission::CreateInvitation)
            && $role->canManage($invitedRole);
    }

    /**
     * Determine whether the user can cancel the invitation (D-077).
     * The owner cancels any, including an owner invitation created before D-089.
     */
    public function cancelInvitation(User $user, Team $team, TeamInvitation $invitation): bool
    {
        $role = $user->teamRole($team);

        return $role !== null
            && $role->hasPermission(TeamPermission::CancelInvitation)
            && ($role === TeamRole::Owner || $role->canManage($invitation->role));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        return ! $team->is_personal && $user->hasTeamPermission($team, TeamPermission::DeleteTeam);
    }
}
