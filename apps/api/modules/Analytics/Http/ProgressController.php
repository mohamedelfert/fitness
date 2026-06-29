<?php

namespace Modules\Analytics\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Analytics\Services\ProgressAnalyzer;

/** Progress analysis + goal projection (FR-AN-001) — `GET /v1/me/progress`. Person-scoped, on read. */
class ProgressController extends Controller
{
    public function show(Request $request, ProgressAnalyzer $analyzer): JsonResponse
    {
        return response()->json(['data' => $analyzer->for($request->user())]);
    }
}
