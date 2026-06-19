<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grounding + dietary-safety support for AI meal-plan generation (FR-AI-002 / FR-AI-007).
 * `slug` gives the Brain a stable, human-readable handle to reference a food by (mirrors
 * exercises.slug), keeping hallucination rare. `dietary_tags` are exclusion flags present
 * in the food (e.g. dairy, pork, nuts, gluten) — the DietaryScanner matches them against a
 * Person's dietary_restrictions, the nutrition analog of exercise contraindications (INV-005).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('source');
            $table->json('dietary_tags')->nullable()->after('region'); // exclusion flags: [dairy, pork, nuts, ...]
        });
    }

    public function down(): void
    {
        Schema::table('food_items', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'dietary_tags']);
        });
    }
};
