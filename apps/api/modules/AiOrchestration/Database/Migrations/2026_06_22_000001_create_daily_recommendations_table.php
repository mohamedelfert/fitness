<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * daily_recommendations (FR-AI-004) — one materialised AI recommendation per Person per day.
 * The unique (person_id, rec_date) key makes generation idempotent for the day: a refresh
 * returns the stored line instead of re-calling the Brain (NFR-AI-001 cost control) or
 * re-charging the wallet. Append-only by intent — a day's recommendation is history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_recommendations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->date('rec_date');
            $table->text('message');
            $table->string('model')->nullable();          // provider model id that produced it
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['person_id', 'rec_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_recommendations');
    }
};
