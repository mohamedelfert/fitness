<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Food database (DATABASE_DESIGN.md §2.3) — licensed/aggregated, localized (A2). Plane A,
 * read-mostly shared asset. Macro columns are per one serving (serving described in
 * serving_units); logging scales them by servings. Indexed by barcode; Meili by name in prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('source')->default('seed');   // usda | openfoodfacts | custom | seed
            $table->string('external_ref')->nullable();
            $table->json('name_i18n');                    // {en, ar, ...}
            $table->string('brand')->nullable();
            $table->string('barcode')->nullable();
            $table->json('serving_units')->nullable();    // [{label, grams}, ...]
            $table->decimal('kcal', 8, 2);
            $table->decimal('protein', 8, 2)->default(0);
            $table->decimal('carbs', 8, 2)->default(0);
            $table->decimal('fat', 8, 2)->default(0);
            $table->json('micros')->nullable();
            $table->string('region')->nullable();
            $table->timestamps();

            $table->index('barcode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_items');
    }
};
