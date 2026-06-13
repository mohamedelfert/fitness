<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programs (DATABASE_DESIGN.md §2.2) — a structured training plan a Person follows.
 * Plane A, person-owned. `source` records who authored it (AI/coach/template/self);
 * `coach_id`/`template_id` are application-level links (P2), nullable in P1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('source')->default('self');   // ai | coach | template | self
            $table->ulid('coach_id')->nullable();
            $table->ulid('template_id')->nullable();
            $table->string('name');
            $table->date('start_date')->nullable();
            $table->json('mesocycle_json')->nullable();
            $table->string('status')->default('active'); // active | completed | archived
            $table->timestamps();

            $table->index(['person_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
