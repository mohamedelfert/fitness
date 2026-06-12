<?php

namespace Modules\Platform\Http;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Liveness/readiness probe for load balancers & monitoring (NFR-OPS / NFR-REL).
 * Database is required (drives 200 vs 503); Redis is reported best-effort so a
 * cache outage degrades rather than fails the probe.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $database = $this->check(fn () => DB::connection()->getPdo() !== null);
        $redis = $this->check(fn () => Redis::connection()->ping() !== null);

        $ok = $database; // only hard dependencies gate readiness
        $status = ! $ok ? 'down' : ($redis ? 'ok' : 'degraded');

        return response()->json([
            'status' => $status,
            'checks' => [
                'database' => $database ? 'ok' : 'down',
                'redis' => $redis ? 'ok' : 'down',
            ],
            'time' => now()->toIso8601String(),
        ], $ok ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (Throwable) {
            return false;
        }
    }
}
