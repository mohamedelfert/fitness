<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workouts (DATABASE_DESIGN.md §2.2) — ordered session templates within a Program.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('program_id')->constrained('programs')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_index');
            $table->string('name');
            $table->unsignedSmallInteger('ordering')->default(0);
            $table->timestamps();

            $table->index(['program_id', 'ordering']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workouts');
    }
};
