<?php

namespace Modules\Analytics\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Analytics\Services\AdherenceAnalyzer;

/** Adherence analytics (FR-AN-002) — `GET /v1/me/adherence`. Person-scoped, on read. */
class AdherenceController extends Controller
{
    public function show(Request $request, AdherenceAnalyzer $analyzer): JsonResponse
    {
        return response()->json(['data' => $analyzer->for($request->user())]);
    }
}
