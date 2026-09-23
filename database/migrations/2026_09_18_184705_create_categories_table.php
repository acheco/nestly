<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('icon');
            $table->string('color');
            $table->uuid('parent_id')->nullable(); // 👈 sin ->constrained() aquí
            $table->string('kind');
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->index(['team_id', 'kind']);
        });

        // FK autoreferencial: en un paso aparte, después de que la tabla
        // (y su primary key) ya existen de verdad — Laravel no permite
        // resolver esto dentro del mismo Schema::create().
        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')
                ->references('id')->on('categories')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
