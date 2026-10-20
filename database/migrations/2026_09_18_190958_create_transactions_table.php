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
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['income', 'expense']); // Income|Expense
            $table->decimal('amount', 14, 2);
            $table->date('date');
            $table->string('description')->nullable();
            $table->string('notes')->nullable();
            $table->uuid('transfer_group_id')->nullable();
            $table->foreignUuid('recurring_id')->nullable()->constrained('recurring_expenses')->nullOnDelete();
            $table->uuid('pot_contribution_id')->nullable();
            $table->timestamps();
        });

        DB::statement('CREATE INDEX idx_tx_team_date ON transactions (team_id, date DESC)');
        DB::statement('CREATE INDEX idx_tx_account ON transactions (account_id)');
        DB::statement('CREATE INDEX idx_tx_category ON transactions (category_id)');
        DB::statement('CREATE INDEX idx_tx_transfer_group ON transactions (transfer_group_id) WHERE transfer_group_id IS NOT NULL');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_positive CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
