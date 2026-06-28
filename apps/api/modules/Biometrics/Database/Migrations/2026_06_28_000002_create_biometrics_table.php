<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * biometrics (FR-BIO-001; DATABASE_DESIGN §2.1) — a Person's body-measurement time series
 * (weight / body fat / circumferences). Append-only & immutable (INV-002), idempotent on
 * client_ulid for offline sync (ADR-005). Plane A (Person-owned). Circumference sites are
 * stored as their own `type` rather than a separate column, keeping the documented
 * (type, value, unit, measured_at) row shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometrics', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('type');                 // weight | body_fat | waist | hip | chest | arm | thigh | neck
            $table->decimal('value', 8, 2);
            $table->string('unit', 16);             // kg | lb | % | cm | in
            $table->ulid('client_ulid')->nullable();
            $table->timestamp('measured_at')->useCurrent();
            $table->timestamps();

            $table->unique(['person_id', 'client_ulid']);
            $table->index(['person_id', 'type', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometrics');
    }
};
