<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * wearable_streams (FR-BIO-003; DATABASE_DESIGN §2.1) — high-write device time series
 * (steps/HR/sleep/HRV). Append-only & immutable (INV-002), idempotent per reading on
 * client_ulid (offline sync, ADR-005). Plane A (Person-owned).
 *
 * ponytail: not range-partitioned here — DATABASE_DESIGN flags monthly partitioning + a TSDB
 * candidate (ARCH §8) at scale; the (person_id, metric, recorded_at) index carries P1 volumes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wearable_streams', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('source')->nullable();   // apple_health | health_connect | manual
            $table->string('metric');                // hr | hrv | sleep | steps
            $table->decimal('value', 12, 2);
            $table->ulid('client_ulid')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['person_id', 'client_ulid']);
            $table->index(['person_id', 'metric', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wearable_streams');
    }
};
