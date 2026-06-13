<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workout exercises (DATABASE_DESIGN.md §2.2) — prescription rows within a Workout.
 * `target_reps`/`target_load` are strings to carry ranges & schemes ("6-8", "AMRAP",
 * "70%", "bodyweight"); performed numbers live in the append-only `set_logs`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('workout_id')->constrained('workouts')->cascadeOnDelete();
            $table->foreignUlid('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(0);
            $table->unsignedSmallInteger('target_sets')->nullable();
            $table->string('target_reps')->nullable();
            $table->string('target_load')->nullable();
            $table->unsignedInteger('rest_sec')->nullable();
            $table->string('tempo')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['workout_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_exercises');
    }
};
