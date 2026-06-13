<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Meal plan days (DATABASE_DESIGN.md §2.2) — ordered days within a MealPlan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plan_days', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meal_plan_id')->constrained('meal_plans')->cascadeOnDelete();
            $table->unsignedSmallInteger('day_index');
            $table->string('name');
            $table->unsignedSmallInteger('ordering')->default(0);
            $table->timestamps();

            $table->index(['meal_plan_id', 'ordering']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_days');
    }
};
