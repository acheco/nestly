<?php

use App\Enums\TeamRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * D-089: before the fix, anyone who could invite could invite as owner. Accepting one of those
 * invitations now hits team_members_one_owner and fails with a 500, so the pending ones go.
 * Accepted ones are history and stay.
 */
return new class extends Migration
{
    public function up(?OutputInterface $output = null): void
    {
        // The migrator calls up() with no arguments; tests pass a buffer to check the report.
        $output ??= app()->runningUnitTests() ? new NullOutput : new ConsoleOutput;

        $deleted = DB::table('team_invitations')
            ->where('role', TeamRole::Owner->value)
            ->whereNull('accepted_at')
            ->delete();

        $output->writeln("D-089: {$deleted} pending owner invitations deleted.");
    }

    /**
     * Irreversible: the deleted invitations can't be restored, and they must not come back.
     */
    public function down(): void {}
};
