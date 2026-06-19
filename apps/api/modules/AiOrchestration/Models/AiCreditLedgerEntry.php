<?php

namespace Modules\AiOrchestration\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One append-only AICredit movement (DATABASE_DESIGN.md §2.5). Never updated or deleted —
 * a wallet's balance is the running sum of its ledger. `delta` is signed (debit < 0).
 */
class AiCreditLedgerEntry extends Model
{
    use HasUlids;

    protected $table = 'ai_credit_ledger';

    /** Append-only: created once, never touched again. */
    public const UPDATED_AT = null;

    protected $fillable = ['wallet_id', 'delta', 'reason', 'ref_type', 'ref_id', 'balance_after'];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'balance_after' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(AiCreditWallet::class, 'wallet_id');
    }
}
