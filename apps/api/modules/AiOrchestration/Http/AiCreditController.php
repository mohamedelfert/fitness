<?php

namespace Modules\AiOrchestration\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiOrchestration\Services\AiCreditMeter;

/**
 * AICredit wallet balance (FR-SAS-004). `GET /v1/me/ai-credits` — auto-provisions an empty
 * wallet on first read so the client always gets a balance, never a 404.
 */
class AiCreditController extends Controller
{
    public function show(Request $request, AiCreditMeter $meter): JsonResponse
    {
        $wallet = $meter->walletFor($request->user());

        return response()->json(['data' => [
            'balance' => $wallet->balance,
            'plan_grant' => $wallet->plan_grant,
            'period_reset_at' => $wallet->period_reset_at?->toIso8601String(),
        ]]);
    }
}
