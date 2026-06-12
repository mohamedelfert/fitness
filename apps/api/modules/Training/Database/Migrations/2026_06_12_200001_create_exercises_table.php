<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Exercise library (DATABASE_DESIGN.md §2.3). Plane A, shared asset. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('primary_muscles')->nullable();
            $table->json('equipment')->nullable();
            $table->json('instructions')->nullable();
            $table->json('contraindications')->nullable(); // powers the AI safety gate (FR-AI-007)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};
