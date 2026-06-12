<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAR-Q+ screening records (FR-AI-007). APPEND-ONLY — each screening is retained
 * for safety/audit; the latest determines persons.health_screen_status. Plane A.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_screens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->json('answers');
            $table->string('result'); // passed | flagged
            $table->json('flagged_questions')->nullable();
            $table->timestamp('screened_at')->useCurrent();
            $table->timestamps();

            $table->index(['person_id', 'screened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('health_screens');
    }
};
