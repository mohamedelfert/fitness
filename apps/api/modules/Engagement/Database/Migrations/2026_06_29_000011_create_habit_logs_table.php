<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * habit_logs (FR-ENG-002) — one completion event for a habit. APPEND-ONLY & IMMUTABLE (INV-002);
 * idempotent on (person_id, client_ulid) for offline sync (ADR-005). person_id is denormalised
 * from the parent habit so the idempotency + ownership checks stay single-table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('habit_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('habit_id')->constrained('habits')->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->ulid('client_ulid')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->unique(['person_id', 'client_ulid']);
            $table->index(['habit_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('habit_logs');
    }
};
