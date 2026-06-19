<?php

namespace Modules\AiOrchestration\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Raised when an AI generation is requested with too few AICredits to cover it. Renders as
 * HTTP 402 Payment Required (API_SPECIFICATION §4 — "AICredits exhausted"). Laravel calls
 * render() automatically, so no handler wiring is needed.
 */
class InsufficientCreditsException extends RuntimeException
{
    public function __construct(
        public readonly int $required = 0,
        public readonly int $balance = 0,
    ) {
        parent::__construct('Insufficient AICredits for this operation.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Insufficient AICredits for this operation.',
            'errors' => [
                'credits' => ['You do not have enough AICredits. Top up to continue.'],
            ],
            'required' => $this->required,
            'balance' => $this->balance,
        ], 402);
    }
}
