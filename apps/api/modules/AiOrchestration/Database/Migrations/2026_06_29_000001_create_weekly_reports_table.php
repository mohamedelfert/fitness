<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * weekly_reports (FR-AN-005) — one materialised AI weekly report per Person per ISO week. The
 * unique (person_id, iso_week) key makes generation idempotent for the week: a refresh returns
 * the stored summary instead of re-calling the Brain (NFR-AI-001 cost control) or re-charging the
 * wallet. iso_week is the ISO-8601 year-week ("2026-W27", year-boundary safe). Append-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('iso_week');                    // "YYYY-Www"
            $table->date('week_start');                    // Monday of that week (for display)
            $table->text('summary');
            $table->string('model')->nullable();           // provider model id that produced it
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['person_id', 'iso_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_reports');
    }
};
