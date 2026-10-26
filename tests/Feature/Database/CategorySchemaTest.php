<?php

use App\Models\Team;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->team = Team::factory()->create();

    // Raw insert on purpose: it bypasses the CategoryKind cast, so the database
    // constraint is the only thing between the value and the table. A closure
    // (not a global function) so it cannot collide with other test files.
    $this->insertRawCategory = function (array $overrides = []): void {
        DB::table('categories')->insert(array_merge([
            'id' => (string) Str::uuid(),
            'team_id' => $this->team->id,
            'name' => 'Raw category',
            'icon' => 'tag',
            'color' => '#000000',
            'kind' => 'expense',
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    };
});

test('a valid kind is accepted', function (string $kind) {
    ($this->insertRawCategory)(['kind' => $kind]);

    expect(DB::table('categories')->where('team_id', $this->team->id)->where('name', 'Raw category')->value('kind'))
        ->toBe($kind);
})->with(['income', 'expense']);

test('an unknown kind is rejected by categories_kind_valid', function () {
    expect(fn () => ($this->insertRawCategory)(['kind' => 'transfer']))
        ->toThrow(QueryException::class, 'categories_kind_valid');
});

test('a new category is not archived by default', function () {
    ($this->insertRawCategory)();

    expect(DB::table('categories')->where('team_id', $this->team->id)->where('name', 'Raw category')->value('archived'))
        ->toBeFalse();
});
