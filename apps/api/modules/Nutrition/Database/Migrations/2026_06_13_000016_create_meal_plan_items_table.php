<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Meal plan items (DATABASE_DESIGN.md §2.2) — prescribed meals within a day (mirrors
 * workout_exercises). References a food_item or a recipe (recipes land in a later slice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_plan_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('meal_plan_day_id')->constrained('meal_plan_days')->cascadeOnDelete();
            $table->string('meal_type'); // breakfast | lunch | dinner | snack
            $table->foreignUlid('food_item_id')->nullable()->constrained('food_items')->nullOnDelete();
            $table->ulid('recipe_id')->nullable(); // FK added with the recipes slice
            $table->decimal('servings', 6, 2)->default(1);
            $table->decimal('target_kcal', 8, 2)->nullable();
            $table->json('target_macros_json')->nullable();
            $table->unsignedSmallInteger('ordering')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['meal_plan_day_id', 'ordering']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_plan_items');
    }
};
