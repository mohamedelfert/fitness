<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bring `exercises` up to the DATABASE_DESIGN.md §2.3 spec — additive only, so the
 * existing set_logs → exercise_id slice is undisturbed. Adds secondary muscles, the
 * mechanics classifier, and media keys (licensed video/instruction playback, FR-TRN-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->json('secondary_muscles')->nullable()->after('primary_muscles');
            $table->string('mechanics')->nullable()->after('equipment'); // compound | isolation
            $table->json('media_keys')->nullable()->after('instructions'); // signed-asset keys (FR-TRN-006)
        });
    }

    public function down(): void
    {
        Schema::table('exercises', function (Blueprint $table) {
            $table->dropColumn(['secondary_muscles', 'mechanics', 'media_keys']);
        });
    }
};
