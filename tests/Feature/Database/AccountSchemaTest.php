<?php

use App\Enums\AccountType;
use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->team = Team::factory()->create();

    // Raw insert on purpose: it bypasses the AccountType cast, so the database
    // constraint is the only thing between the value and the table. A team
    // account with no owner, so accounts_visibility_owner_consistent stays quiet.
    $this->insertRawAccount = function (array $overrides = []): void {
        DB::table('accounts')->insert(array_merge([
            'id' => (string) Str::uuid(),
            'team_id' => $this->team->id,
            'name' => 'Raw account',
            'type' => AccountType::Checking->value,
            'visibility' => 'team',
            'owner_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    };
});

// Dataset built from the enum: a case added to AccountType but not to the
// CHECK makes this test fail, which is exactly the drift we want to catch.
test('every AccountType case is accepted', function (string $type) {
    ($this->insertRawAccount)(['type' => $type]);

    expect(DB::table('accounts')->where('team_id', $this->team->id)->where('name', 'Raw account')->value('type'))
        ->toBe($type);
})->with(array_column(AccountType::cases(), 'value'));

test('an unknown type is rejected by accounts_type_valid', function () {
    expect(fn () => ($this->insertRawAccount)(['type' => 'loan']))
        ->toThrow(QueryException::class, 'accounts_type_valid');
});
