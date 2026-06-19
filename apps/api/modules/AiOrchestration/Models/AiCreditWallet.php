<?php

namespace Modules\AiOrchestration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A metered AICredit balance (DATABASE_DESIGN.md §2.5). Owned by a Person (or, later, a
 * tenant) via the polymorphic (owner_type, owner_id) pair. `balance` is mutated only through
 * AiCreditMeter's guarded debit/grant so it stays in lock-step with the append-only ledger.
 */
class AiCreditWallet extends Model
{
    use HasUlids;

    protected $fillable = ['owner_type', 'owner_id', 'balance', 'plan_grant', 'period_reset_at'];

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'plan_grant' => 'integer',
            'period_reset_at' => 'datetime',
        ];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(AiCreditLedgerEntry::class, 'wallet_id');
    }
}
