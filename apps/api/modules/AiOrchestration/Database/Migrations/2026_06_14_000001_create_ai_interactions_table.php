<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ai_interactions (DATABASE_DESIGN.md §2.5) — one row per Brain call. Every call is
 * logged, including rejected and errored ones: this is the audit trail behind the
 * safety gate (FR-AI-007 / INV-005), the AI-cost dashboards (NFR-OPS-002), and the
 * graduated-autonomy learning signal (FR-AI-010). Plane A; `tenant_id` nullable for B2C.
 *
 * Note: DATABASE_DESIGN §4 calls for monthly range-partitioning on `created_at` at scale.
 * Deferred — partitioning is a prod-DB operation, not expressed in the portable schema here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_interactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->ulid('tenant_id')->nullable();          // Plane B context, null for B2C
            $table->string('feature');                       // program | meal_plan | swap | chat
            $table->string('model')->nullable();             // provider model id
            $table->string('tier')->nullable();              // strong | cheap (model-tiering, ARCH §6)
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0); // integer minor units (INV-006)
            $table->unsignedInteger('latency_ms')->default(0);
            $table->unsignedTinyInteger('confidence')->nullable();  // 0-100, model self-report
            $table->string('safety_verdict');                // passed | rejected | error
            $table->boolean('accepted')->nullable();         // user accepted the output (FR-AI-010)
            $table->timestamp('created_at')->useCurrent();

            $table->index(['person_id', 'feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_interactions');
    }
};
