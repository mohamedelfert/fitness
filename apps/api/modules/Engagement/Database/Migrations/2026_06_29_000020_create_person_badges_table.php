<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * person_badges (FR-ENG-003; DATABASE_DESIGN §2.4) — a Person's earned badges. A badge is a
 * HISTORICAL award, so it is persisted here on first threshold-cross and never recomputed: once
 * earned it stays earned. The badge *catalog* (slug → name → criterion) is config, not a table
 * (`gamification.badges`). Unique (person_id, badge_slug) makes award idempotent. Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_badges', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('badge_slug');                   // see gamification.badges catalog
            $table->timestamp('earned_at')->useCurrent();

            $table->unique(['person_id', 'badge_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_badges');
    }
};
