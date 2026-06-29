<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * habits (FR-ENG-002; DATABASE_DESIGN §2.4) — a Person-owned recurring intention (e.g. "drink
 * water", "stretch"). Plane A, scoped by person_id. Completions are recorded in the append-only
 * habit_logs child. `target_per_period` is how many completions count as "done" for the cadence.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('name');
            $table->string('cadence')->default('daily');     // daily | weekly (see Habit::CADENCES)
            $table->unsignedSmallInteger('target_per_period')->default(1);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['person_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habits');
    }
};
