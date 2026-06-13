<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Personal records (DATABASE_DESIGN.md §2.2) — a denormalized read-model of the Person's
 * current best per (exercise, metric), derived async from the append-only set_logs (FR-TRN-004).
 * One row per (person, exercise, metric); refreshed by DetectPersonalRecords, never on the hot path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personal_records', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignUlid('exercise_id')->constrained('exercises')->cascadeOnDelete();
            $table->string('metric'); // max_load | est_1rm | max_reps
            $table->decimal('value', 10, 2);
            $table->timestamp('achieved_at')->nullable();
            $table->ulid('session_id')->nullable();
            $table->timestamps();

            $table->unique(['person_id', 'exercise_id', 'metric']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_records');
    }
};
