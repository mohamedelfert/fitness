<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MealPlans (DATABASE_DESIGN.md §2.2) — the nutrition analog of programs. Plane A,
 * person-owned. `source` records who authored it; AI generation (E1.6) populates it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('source')->default('self'); // ai | coach | template | self
            $table->ulid('coach_id')->nullable();
            $table->ulid('template_id')->nullable();
            $table->string('name');
            $table->json('daily_targets_json')->nullable(); // {kcal, protein, carbs, fat}
            $table->date('start_date')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plans');
    }
};
