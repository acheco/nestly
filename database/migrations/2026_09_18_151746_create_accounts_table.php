<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->decimal('initial_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->enum('visibility', ['team', 'private'])->default('team');
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->noActionOnDelete();
            $table->boolean('archived')->default(false);
            $table->timestamps();

            $table->index(['team_id', 'archived']);
        });

        DB::statement("ALTER TABLE accounts ADD CONSTRAINT accounts_visibility_owner_consistent CHECK ((visibility = 'private') = (owner_id IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
