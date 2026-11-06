<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    // Raw insert on purpose: it bypasses every policy and action, so the index
    // is the only thing between a second owner and the table (D-089).
    $this->insertMembership = function (Team $team, string $role): void {
        DB::table('team_members')->insert([
            'id' => (string) Str::uuid(),
            'team_id' => $team->id,
            'user_id' => User::factory()->create()->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    };
});

test('a second owner in the same team is rejected by team_members_one_owner', function () {
    $team = Team::factory()->create();
    ($this->insertMembership)($team, TeamRole::Owner->value);

    // The savepoint keeps the test's own transaction usable after the expected failure.
    expect(fn () => DB::transaction(fn () => ($this->insertMembership)($team, TeamRole::Owner->value)))
        ->toThrow(QueryException::class, 'team_members_one_owner');

    expect(DB::table('team_members')->where('team_id', $team->id)->where('role', TeamRole::Owner->value)->count())->toBe(1);
});

test('the index only limits owners', function () {
    $team = Team::factory()->create();
    ($this->insertMembership)($team, TeamRole::Owner->value);

    foreach ([TeamRole::Admin, TeamRole::Admin, TeamRole::Member, TeamRole::Member, TeamRole::Viewer, TeamRole::Viewer] as $role) {
        ($this->insertMembership)($team, $role->value);
    }

    expect(DB::table('team_members')->where('team_id', $team->id)->count())->toBe(7);
});

test('each team can have its own owner', function () {
    $first = Team::factory()->create();
    $second = Team::factory()->create();

    ($this->insertMembership)($first, TeamRole::Owner->value);
    ($this->insertMembership)($second, TeamRole::Owner->value);

    // Scoped to these teams: the user factory also creates personal teams, each with its owner.
    expect(DB::table('team_members')->whereIn('team_id', [$first->id, $second->id])->where('role', TeamRole::Owner->value)->count())->toBe(2);
});
