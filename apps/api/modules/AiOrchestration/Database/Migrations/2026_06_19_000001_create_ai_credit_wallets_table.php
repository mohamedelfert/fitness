<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AICredit wallets (DATABASE_DESIGN.md §2.5) — the metered AI usage balance. Plane A,
 * owned by a Person or (later) a tenant; the (owner_type, owner_id) pair is the wallet's
 * identity, so it's unique. `balance` is the live credit count; debits/credits are journaled
 * in ai_credit_ledger. `owner_id` is intentionally NOT a FK (polymorphic owner).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_credit_wallets', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('owner_type'); // person | tenant
            $table->ulid('owner_id');
            $table->integer('balance')->default(0);
            $table->integer('plan_grant')->default(0); // recurring allotment from the active plan (E1.9)
            $table->timestamp('period_reset_at')->nullable();
            $table->timestamps();

            $table->unique(['owner_type', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_credit_wallets');
    }
};
