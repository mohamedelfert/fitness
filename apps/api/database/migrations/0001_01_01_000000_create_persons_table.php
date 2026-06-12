<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Person — the portable, user-owned identity (GLOSSARY.md, DATABASE_DESIGN.md §2.1).
 * Plane A (central). ULID primary key (ADR-009). Not tenant-scoped.
 *
 * NOTE: the framework HTTP-session table is intentionally NOT created here — this is a
 * token-based API (Sanctum) using the `file` session driver — so the `sessions` name is
 * reserved for the domain training-session table created by the Training module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persons', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('display_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Profile basics used by the AI Brain
            $table->date('dob')->nullable();
            $table->string('sex')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();

            // i18n / residency (A2)
            $table->string('locale', 5)->default('en');           // en | ar
            $table->string('unit_system', 10)->default('metric');  // metric | imperial
            $table->string('timezone')->default('UTC');
            $table->string('country', 2)->nullable();

            // PAR-Q+ safety gate status (FR-AI-007)
            $table->string('health_screen_status')->default('none'); // none | passed | flagged
            $table->json('onboarding_state')->nullable();

            $table->rememberToken();
            $table->timestamps();

            $table->index('country');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('persons');
    }
};
