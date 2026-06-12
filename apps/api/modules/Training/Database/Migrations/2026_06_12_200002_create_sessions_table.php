<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Training Session — a performed Workout instance (DATABASE_DESIGN.md §2.2). Plane A.
 * (The framework HTTP-session table is intentionally not used; see create_persons_table.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->ulid('workout_id')->nullable();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedTinyInteger('perceived_effort')->nullable();
            $table->string('source')->default('manual');
            $table->timestamps();

            $table->index(['person_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
