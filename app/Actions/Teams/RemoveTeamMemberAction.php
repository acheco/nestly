<?php

namespace App\Actions\Teams;

use App\Enums\AccountVisibility;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Takes a member out of a team, whether they leave or are removed (PRD RF-02.7, ERD §4.3.8).
 *
 * Their private accounts in that team are archived in the same transaction: their transfers
 * are part of the team's history, so the accounts stay (with owner_id), but nobody can use
 * them and nobody sees their balance. If the member comes back, they can reactivate them.
 *
 * This is an action and not a Membership observer on purpose: the membership is removed with
 * a mass delete, which never fires Eloquent model events, so an observer would silently not run.
 */
final class RemoveTeamMemberAction
{
    /**
     * @throws Throwable
     */
    public function execute(Team $team, User $member): void
    {
        DB::transaction(function () use ($team, $member) {
            $team->memberships()
                ->where('user_id', $member->id)
                ->delete();

            // Mass update: archiving touches no balance or ledger row, so there is no invariant
            // to recompute. TODO(audit): log this change explicitly once audit_logs exists (Phase 9),
            // since a mass update fires no model events either.
            $team->accounts()
                ->where('visibility', AccountVisibility::Private)
                ->where('owner_id', $member->id)
                ->update(['archived' => true]);
        });
    }
}
