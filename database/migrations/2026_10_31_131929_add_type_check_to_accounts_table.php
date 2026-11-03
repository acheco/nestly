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
        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_type_valid CHECK (type IN ('checking', 'savings', 'cash', 'credit', 'investment'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE accounts DROP CONSTRAINT accounts_type_valid');
    }
};
