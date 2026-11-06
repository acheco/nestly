<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use Symfony\Component\Console\Output\BufferedOutput;

/*
| D-089: owner invitations created before the fix would hit team_members_one_owner
| on accept and fail with a 500. The migration deletes the pending ones.
*/

beforeEach(function () {
    $this->migration = require database_path('migrations/2026_11_05_130000_delete_pending_owner_invitations.php');
    $this->team = Team::factory()->create();
    $this->invitation = fn (TeamRole $role, array $state = []) => TeamInvitation::factory()->create(array_merge([
        'team_id' => $this->team->id,
        'role' => $role,
    ], $state));
});

test('pending owner invitations are deleted and counted', function () {
    $pending = ($this->invitation)(TeamRole::Owner);
    $expired = ($this->invitation)(TeamRole::Owner, ['expires_at' => now()->subDay()]);
    $output = new BufferedOutput;

    $this->migration->up($output);

    expect(TeamInvitation::find($pending->id))->toBeNull()
        ->and(TeamInvitation::find($expired->id))->toBeNull()
        ->and($output->fetch())->toContain('2 pending owner invitations deleted');
});

test('accepted owner invitations and other roles are kept', function () {
    $accepted = ($this->invitation)(TeamRole::Owner, ['accepted_at' => now()]);
    $admin = ($this->invitation)(TeamRole::Admin);
    $member = ($this->invitation)(TeamRole::Member);
    $output = new BufferedOutput;

    $this->migration->up($output);

    expect(TeamInvitation::find($accepted->id))->not->toBeNull()
        ->and(TeamInvitation::find($admin->id))->not->toBeNull()
        ->and(TeamInvitation::find($member->id))->not->toBeNull()
        ->and($output->fetch())->toContain('0 pending owner invitations deleted');
});
