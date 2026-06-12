<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SetLog — the densest table; APPEND-ONLY & IMMUTABLE (INV-002, DATABASE_DESIGN.md §2.2).
 * Corrections are new rows, never updates. client_ulid makes offline sync idempotent (ADR-005).
 * (Production: range-partition by logged_at — deferred past the skeleton.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('set_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('sessions')->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignUlid('exercise_id')->constrained('exercises');
            $table->unsignedInteger('set_index');
            $table->unsignedInteger('reps');
            $table->decimal('load', 8, 2)->nullable();
            $table->decimal('rpe', 3, 1)->nullable();
            $table->decimal('rir', 3, 1)->nullable();
            $table->ulid('client_ulid')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            // Idempotency: a given client-generated id resolves to exactly one row per person.
            // (MySQL/MariaDB permit multiple NULLs, so non-idempotent inserts are unaffected.)
            $table->unique(['person_id', 'client_ulid']);
            $table->index(['person_id', 'exercise_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('set_logs');
    }
};
