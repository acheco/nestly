<?php

namespace App\Enums;

enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * Get the display label for the role.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get all the permissions for this role.
     *
     * @return array<TeamPermission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => TeamPermission::cases(),
            self::Admin => [
                TeamPermission::UpdateTeam,
                // D-077: limited to members and viewers by TeamRole::canManage().
                TeamPermission::UpdateMember,
                TeamPermission::RemoveMember,
                TeamPermission::CreateInvitation,
                TeamPermission::CancelInvitation,
            ],
            self::Member => [],
            self::Viewer => [],
        };
    }

    /**
     * Determine if the role has the given permission.
     */
    public function hasPermission(TeamPermission $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Get the hierarchy level for this role.
     * Higher numbers indicate higher privileges.
     */
    public function level(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Admin => 2,
            self::Member => 1,
            self::Viewer => 0,
        };
    }

    /**
     * Check if this role is at least as privileged as another role.
     */
    public function isAtLeast(TeamRole $role): bool
    {
        return $this->level() >= $role->level();
    }

    /**
     * D-077: the owner manages every role but owner; an admin manages members and viewers only.
     * Used both for the member acted on and for the role being assigned.
     */
    public function canManage(TeamRole $target): bool
    {
        return match ($this) {
            self::Owner => $target !== self::Owner,
            self::Admin => $target === self::Member || $target === self::Viewer,
            self::Member, self::Viewer => false,
        };
    }

    /**
     * The roles this role may assign. Never owner (D-089).
     *
     * @return list<TeamRole>
     */
    public function assignableRoles(): array
    {
        return array_values(array_filter(self::cases(), fn (self $role) => $this->canManage($role)));
    }

    /**
     * Get the roles that can be assigned to team members (excludes Owner).
     *
     * @return array<array{value: string, label: string}>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->filter(fn (self $role) => $role !== self::Owner)
            ->map(fn (self $role) => ['value' => $role->value, 'label' => $role->label()])
            ->values()
            ->toArray();
    }
}
