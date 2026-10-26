<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_kind_valid CHECK (kind IN ('income', 'expense'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE categories DROP CONSTRAINT categories_kind_valid');
    }
};
