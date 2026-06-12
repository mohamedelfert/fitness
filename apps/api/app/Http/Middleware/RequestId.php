<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlates every request with a stable id (NFR-OPS-001). Honors an inbound
 * X-Request-Id, otherwise mints one; binds it to the log Context so every log
 * line carries it, and echoes it back on the response. Matches the `request_id`
 * field in the API error model (API_SPECIFICATION.md §1.1).
 */
class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get('X-Request-Id') ?: (string) Str::ulid();

        $request->headers->set('X-Request-Id', $id);
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
