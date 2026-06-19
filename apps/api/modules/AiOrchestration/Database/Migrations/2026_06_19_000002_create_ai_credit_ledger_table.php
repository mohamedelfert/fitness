<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AICredit ledger (DATABASE_DESIGN.md §2.5) — append-only journal of every wallet movement.
 * Single-entry signed `delta` (negative = debit, positive = grant/top-up), NOT the Plane-B
 * double-entry money ledger (INV-003 does not apply here). `balance_after` snapshots the
 * post-movement balance for cheap auditing; `(ref_type, ref_id)` links a debit to what it
 * paid for (e.g. the generated Program) for audit/traceback — it is NOT a uniqueness
 * constraint (no double-charge today comes from each generation minting a fresh program id;
 * add a unique index here if a retry path ever needs true per-artefact idempotency).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credit_ledger', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('wallet_id')->constrained('ai_credit_wallets')->cascadeOnDelete();
            $table->integer('delta'); // signed: <0 debit, >0 credit
            $table->string('reason'); // program_generation | free_grant | topup | plan_grant
            $table->string('ref_type')->nullable();
            $table->ulid('ref_id')->nullable();
            $table->integer('balance_after');
            $table->timestamp('created_at')->nullable(); // append-only: no updated_at

            $table->index(['wallet_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_ledger');
    }
};
