<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Goals (FR-ENG-001; DATABASE_DESIGN.md §2.2). A Person-owned target — fat loss,
 * strength, hypertrophy, an event, or a health metric — time-bound. Plane A (central);
 * scoped by person_id, never tenant. Captured during onboarding, tracked thereafter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('type');                          // see Goal::TYPES
            $table->decimal('target_value', 10, 2)->nullable();
            $table->string('target_unit')->nullable();       // kg | lb | % | reps | ...
            $table->date('target_date')->nullable();
            $table->string('status')->default('active');     // active | achieved | abandoned
            $table->timestamps();

            $table->index(['person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
