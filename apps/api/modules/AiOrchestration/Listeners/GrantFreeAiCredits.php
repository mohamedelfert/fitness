<?php

namespace Modules\AiOrchestration\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\AiOrchestration\Models\AiCreditLedgerEntry;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\Identity\Events\OnboardingCompleted;

/**
 * Grants the one-time free AICredit starter balance when a Person finishes onboarding
 * (FR-SAS-004, pre-billing stopgap until E1.9 plan grants). Wallets start empty, so without
 * this nobody can use any AI feature on first run.
 *
 * Best-effort by design: a failure here must NOT 500 a successful onboarding (the grant is a
 * perk, not part of the critical path). Worst case degrades to a clean 402 on first AI use.
 */
class GrantFreeAiCredits
{
    public function __construct(private readonly AiCreditMeter $meter) {}

    public function handle(OnboardingCompleted $event): void
    {
        $amount = (int) config('ai.credits.free_grant', 0);
        if ($amount <= 0) {
            return;
        }

        try {
            $wallet = $this->meter->walletFor($event->person);

            // Idempotency backstop: never grant twice, regardless of how often the event fires.
            // ponytail: this read-then-grant can race two concurrent first-submits into a double
            // grant. Stakes = one extra free grant; add a unique ledger (wallet_id, reason='free_grant')
            // index if that ever matters.
            $alreadyGranted = AiCreditLedgerEntry::where('wallet_id', $wallet->id)
                ->where('reason', 'free_grant')
                ->exists();

            if (! $alreadyGranted) {
                $this->meter->grant($event->person, $amount, 'free_grant');
            }
        } catch (\Throwable $e) {
            Log::warning('Free AICredit grant failed; person can top up / will hit 402 on first AI use', [
                'person_id' => $event->person->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
