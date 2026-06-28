<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * coach_messages (FR-AI-008) — the conversational AI coach's transcript, one implicit thread
 * per Person (no thread management in P1). Append-only: each turn is a user row then an
 * assistant row. The (person_id, created_at) index serves both history reads and the
 * recent-history replay that gives the chat its multi-turn context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coach_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('person_id')->constrained('persons')->cascadeOnDelete();
            $table->string('role'); // user | assistant
            $table->text('content');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['person_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coach_messages');
    }
};
