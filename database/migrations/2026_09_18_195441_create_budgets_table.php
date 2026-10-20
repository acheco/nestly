<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->decimal('limit_amount', 14, 2);
            $table->enum('period', ['weekly', 'monthly', 'yearly']);
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('rollover')->default(false);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE budgets
              ADD COLUMN period_range daterange
              GENERATED ALWAYS AS (daterange(start_date, end_date, '[]')) STORED
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE budgets
              ADD CONSTRAINT budgets_no_overlap
              EXCLUDE USING gist (
                team_id WITH =,
                category_id WITH =,
                period_range WITH &&
              )
        SQL);

        DB::statement('CREATE INDEX idx_budget_team_period ON budgets (team_id, start_date, end_date)');
        DB::statement('ALTER TABLE budgets ADD CONSTRAINT budgets_limit_amount_positive CHECK (limit_amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
