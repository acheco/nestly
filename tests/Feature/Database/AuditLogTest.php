<?php

use App\Enums\TeamRole;
use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Team;

test('an audit log entry can be stored and read back', function () {
    $team = Team::factory()->create();
    $user = teamUser($team, TeamRole::Owner);
    $account = Account::factory()->for($team)->create();

    $log = AuditLog::create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'action' => 'updated',
        'model_type' => $account->getMorphClass(),
        'model_id' => $account->id,
        'changes' => ['name' => ['old' => 'A', 'new' => 'B']],
        'ip' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    $stored = AuditLog::findOrFail($log->id);

    expect($stored->team->is($team))->toBeTrue()
        ->and($stored->user->is($user))->toBeTrue()
        ->and($stored->model->is($account))->toBeTrue()
        ->and($stored->changes)->toBe(['name' => ['old' => 'A', 'new' => 'B']])
        ->and($stored->created_at)->not->toBeNull();
});
