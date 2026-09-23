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
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('name');
            $table->decimal('amount', 14, 2);
            $table->string('frequency');
            $table->integer('interval')->default(1);
            $table->date('next_run_date');
            $table->date('end_date')->nullable();
            $table->integer('notify_days_before')->default(3);
            $table->boolean('active')->default(true);
            $table->boolean('auto_create_transaction')->default(true);
            $table->timestamps();
        });

        DB::statement('CREATE INDEX idx_recurring_next_run ON recurring_expenses (next_run_date) WHERE active = true');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_expenses');
    }
};
