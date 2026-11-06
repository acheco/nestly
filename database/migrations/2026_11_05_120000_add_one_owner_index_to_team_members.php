<?php

use App\Enums\TeamRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-089: a team has exactly one owner. This index stops a second one at the database level.
 * It can't stop a team from having none; the policies and actions cover that.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Runs before any change, so existing bad data stops the migration instead of failing halfway.
        $teams = (int) DB::scalar(
            'SELECT COUNT(*) FROM (SELECT team_id FROM team_members WHERE role = ? GROUP BY team_id HAVING COUNT(*) > 1) AS t',
            [TeamRole::Owner->value],
        );

        if ($teams > 0) {
            throw new RuntimeException("D-089: {$teams} teams have more than one owner. Fix them before migrating.");
        }

        // DDL takes no bind parameters; the value comes from the enum, not from input.
        $owner = TeamRole::Owner->value;
        DB::statement("CREATE UNIQUE INDEX team_members_one_owner ON team_members (team_id) WHERE role = '{$owner}'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX team_members_one_owner');
    }
};
