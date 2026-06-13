<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FoodLog (DATABASE_DESIGN.md §2.2) — a logged consumption event. APPEND-ONLY & IMMUTABLE
 * (INV-002); macros are snapshotted at log time so the entry survives food_item changes.
 * client_ulid makes offline sync idempotent (ADR-005). (Prod: range-partition by logged_at.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignUlid('food_item_id')->nullable()->constrained('food_items')->nullOnDelete();
            $table->ulid('recipe_id')->nullable(); // FK added with the recipes slice
            $table->string('meal_type');           // breakfast | lunch | dinner | snack
            $table->decimal('servings', 6, 2)->default(1);
            $table->decimal('kcal', 8, 2);
            $table->decimal('protein', 8, 2)->default(0);
            $table->decimal('carbs', 8, 2)->default(0);
            $table->decimal('fat', 8, 2)->default(0);
            $table->json('micros_json')->nullable();
            $table->string('source')->default('search'); // search | barcode | image | voice | custom
            $table->ulid('client_ulid')->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->timestamps();

            $table->unique(['person_id', 'client_ulid']);
            $table->index(['person_id', 'logged_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_logs');
    }
};
