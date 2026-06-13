<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplement intake events (FR-NUT-007; DATABASE_DESIGN.md §2.2). Append-only & immutable
 * (INV-002), idempotent on client_ulid. Plane A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplement_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('dose', 8, 2)->nullable();
            $table->string('unit')->nullable(); // g | mg | iu | capsule | ...
            $table->ulid('client_ulid')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->unique(['person_id', 'client_ulid']);
            $table->index(['person_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplement_logs');
    }
};
