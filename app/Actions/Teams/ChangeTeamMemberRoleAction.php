<?php

namespace App\Actions\Teams;

use App\Enums\AccountVisibility;
use App\Enums\TeamRole;
use App\Models\Membership;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Changes a member's role. Authorization (D-077, D-089) is the caller's job.
 *
 * D-017: a viewer can't have private accounts (D-011), so demoting someone to viewer archives
 * their private accounts in that team in the same transaction, as leaving does (D-016). The
 * accounts keep owner_id and their history. Going back to member doesn't reactivate them:
 * the member does that.
 */
final class ChangeTeamMemberRoleAction
{
    /**
     * @throws Throwable
     */
    public function execute(Team $team, Membership $membership, TeamRole $newRole): void
    {
        DB::transaction(function () use ($team, $membership, $newRole) {
            $membership->update(['role' => $newRole]);

            if ($newRole !== TeamRole::Viewer) {
                return;
            }

            // Mass update: archiving touches no balance or ledger row, so there is no invariant
            // to recompute. TODO(audit): log this change explicitly once audit_logs exists (Phase 9),
            // since a mass update fires no model events either.
            $team->accounts()
                ->where('visibility', AccountVisibility::Private)
                ->where('owner_id', $membership->user_id)
                ->update(['archived' => true]);
        });
    }
}
