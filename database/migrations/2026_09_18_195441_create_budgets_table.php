<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Requerida para el exclusion constraint (combina igualdad + rango
        // en el mismo constraint) — ver ERD §4.4.1.
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');

        Schema::create('budgets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->decimal('limit_amount', 14, 2);
            $table->string('period'); // weekly|monthly|yearly
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('rollover')->default(false);
            $table->string('scope'); // personal|shared
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Columna generada: rango [start_date, end_date] inclusivo,
        // recalculada automáticamente por Postgres — no se escribe a mano.
        DB::statement(<<<'SQL'
            ALTER TABLE budgets
              ADD COLUMN period_range daterange
              GENERATED ALWAYS AS (daterange(start_date, end_date, '[]')) STORED
        SQL);

        // Exclusion constraint (ERD §4.4.1): no puede haber 2 budgets con
        // mismo team+category+scope(+owner si personal) cuyo period_range
        // se solape. COALESCE normaliza owner_id null (scope=shared) a un
        // UUID fijo para que la comparación de igualdad funcione también
        // en ese caso.
        DB::statement(<<<'SQL'
            ALTER TABLE budgets
              ADD CONSTRAINT budgets_no_overlap
              EXCLUDE USING gist (
                team_id WITH =,
                category_id WITH =,
                scope WITH =,
                COALESCE(owner_id, '00000000-0000-0000-0000-000000000000'::uuid) WITH =,
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
