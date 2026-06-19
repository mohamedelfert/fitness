<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\AiOrchestration\Exceptions\InsufficientCreditsException;
use Modules\AiOrchestration\Models\AiCreditLedgerEntry;
use Modules\AiOrchestration\Models\AiCreditWallet;
use Modules\Identity\Models\Person;

/**
 * The AICredit meter (FR-SAS-004): the single place that moves credits. Every mutation is a
 * guarded, atomic balance update paired with an append-only ledger row, so the wallet's
 * `balance` can never drift from its journal nor go negative.
 *
 * Usage contract for a generation: ensureCanAfford() up front (→ 402 if broke), run the
 * generation, then debit() once on success. Failed/rejected generations are never debited —
 * the safety reject-and-regenerate loop (INV-005) must not cost the user credits.
 */
class AiCreditMeter
{
    /** Credits a given AI feature costs (config-driven, falls back to default). */
    public function costFor(string $feature): int
    {
        return (int) config("ai.credits.{$feature}", config('ai.credits.default', 1));
    }

    /** The Person's wallet, created empty (balance 0) on first touch. */
    public function walletFor(Person $person): AiCreditWallet
    {
        return AiCreditWallet::firstOrCreate(
            ['owner_type' => 'person', 'owner_id' => $person->id],
            ['balance' => 0, 'plan_grant' => 0],
        );
    }

    public function balance(Person $person): int
    {
        return $this->walletFor($person)->balance;
    }

    public function hasCredits(Person $person, int $credits): bool
    {
        return $this->balance($person) >= $credits;
    }

    /** Throw a 402-rendering exception unless the Person can cover `credits`. */
    public function ensureCanAfford(Person $person, int $credits): void
    {
        $balance = $this->balance($person);
        if ($balance < $credits) {
            throw new InsufficientCreditsException(required: $credits, balance: $balance);
        }
    }

    /**
     * Atomically debit `credits` and journal it. The guarded UPDATE (balance >= credits)
     * is the integrity backstop: if a concurrent debit drained the wallet since the caller
     * checked, zero rows match and we refuse rather than go negative.
     *
     * @throws InsufficientCreditsException when the wallet can no longer cover the cost
     */
    public function debit(Person $person, int $credits, string $reason, ?Model $ref = null): AiCreditLedgerEntry
    {
        return $this->move($person, -abs($credits), $reason, $ref);
    }

    /** Grant (credit) `credits` to the Person's wallet and journal it. */
    public function grant(Person $person, int $credits, string $reason, ?Model $ref = null): AiCreditLedgerEntry
    {
        return $this->move($person, abs($credits), $reason, $ref);
    }

    /** Apply a signed delta to the wallet and append the matching ledger row, atomically. */
    private function move(Person $person, int $delta, string $reason, ?Model $ref): AiCreditLedgerEntry
    {
        $id = $this->walletFor($person)->id;

        return DB::transaction(function () use ($id, $delta, $reason, $ref) {
            // Row-lock the wallet for the whole read-modify-write so concurrent debits
            // serialize — the integrity backstop against a negative balance / lost update.
            $wallet = AiCreditWallet::whereKey($id)->lockForUpdate()->first();

            if ($delta < 0 && $wallet->balance < abs($delta)) {
                throw new InsufficientCreditsException(required: abs($delta), balance: $wallet->balance);
            }

            $wallet->balance += $delta;
            $wallet->save();

            return AiCreditLedgerEntry::create([
                'wallet_id' => $wallet->id,
                'delta' => $delta,
                'reason' => $reason,
                'ref_type' => $ref?->getMorphClass(),
                'ref_id' => $ref?->getKey(),
                'balance_after' => $wallet->balance,
            ]);
        });
    }
}
