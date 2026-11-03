<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D-074: a foreign key on `id` alone only proves the row exists, not that it belongs to the same
 * team. These composite keys make PostgreSQL refuse a reference to another team.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array REFERENCED_TABLES = ['accounts', 'categories', 'recurring_expenses'];

    /**
     * Each entry is [table, column, referenced table, composite ON DELETE, original ON DELETE].
     *
     * SET NULL names its column: on its own it would also null team_id, which is NOT NULL.
     *
     * @var list<array{string, string, string, string, string}>
     */
    private const array FOREIGN_KEYS = [
        ['transactions', 'account_id', 'accounts', 'RESTRICT', 'RESTRICT'],
        ['transactions', 'category_id', 'categories', 'RESTRICT', 'RESTRICT'],
        ['transactions', 'recurring_id', 'recurring_expenses', 'SET NULL (recurring_id)', 'SET NULL'],
        ['recurring_expenses', 'account_id', 'accounts', 'RESTRICT', 'RESTRICT'],
        ['recurring_expenses', 'category_id', 'categories', 'RESTRICT', 'RESTRICT'],
        ['budgets', 'category_id', 'categories', 'RESTRICT', 'RESTRICT'],
        ['categories', 'parent_id', 'categories', 'RESTRICT', 'RESTRICT'],
    ];

    public function up(): void
    {
        $this->abortIfAnyRowCrossesTeams();

        foreach (self::REFERENCED_TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_id_team_id_unique UNIQUE (id, team_id)");
        }

        foreach (self::FOREIGN_KEYS as [$table, $column, $referenced, $onDelete]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_{$column}_foreign");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_team_foreign FOREIGN KEY ({$column}, team_id) REFERENCES {$referenced} (id, team_id) ON DELETE {$onDelete}");
        }
    }

    public function down(): void
    {
        foreach (self::FOREIGN_KEYS as [$table, $column, $referenced, , $onDelete]) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_{$column}_team_foreign");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_{$column}_foreign FOREIGN KEY ({$column}) REFERENCES {$referenced} (id) ON DELETE {$onDelete}");
        }

        foreach (self::REFERENCED_TABLES as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$table}_id_team_id_unique");
        }
    }

    /** Runs before any change, so existing bad data stops the migration instead of being silently kept. */
    private function abortIfAnyRowCrossesTeams(): void
    {
        foreach (self::FOREIGN_KEYS as [$table, $column, $referenced]) {
            $crossing = DB::table("{$table} as child")
                ->join("{$referenced} as parent", 'parent.id', '=', "child.{$column}")
                ->whereColumn('parent.team_id', '<>', 'child.team_id')
                ->count();

            if ($crossing > 0) {
                throw new RuntimeException("D-074: {$crossing} rows in {$table}.{$column} point to another team. Fix them before migrating.");
            }
        }
    }
};
