<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Water intake events (FR-NUT-006; DATABASE_DESIGN.md §2.2). Append-only & immutable (INV-002),
 * idempotent on client_ulid (offline sync, ADR-005). Plane A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->unsignedInteger('amount_ml');
            $table->ulid('client_ulid')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->unique(['person_id', 'client_ulid']);
            $table->index(['person_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_logs');
    }
};
