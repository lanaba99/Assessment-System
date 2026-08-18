<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group System
 */
class SystemController extends Controller
{
    /**
     * Existing "database" field/behavior is unchanged — "redis" is a new,
     * additive field. Both checks fail closed (any exception, including a
     * missing driver/extension, reports "unavailable" rather than crashing
     * this endpoint) and never expose the underlying exception message —
     * only Laravel's own driver/config names ever appear here, never a
     * connection string, credential, or stack trace.
     */
    public function status(): JsonResponse
    {
        $databaseConnected = $this->databaseIsReachable();
        $redisConnected = $this->redisIsReachable();
        $tenantId = tenant() !== null ? (string) tenant()->getKey() : null;
        $healthy = $databaseConnected && $redisConnected !== 'unavailable';

        return new JsonResponse([
            'data' => [
                'status' => $healthy ? 'ok' : 'degraded',
                'tenant_id' => $tenantId,
                'database' => $databaseConnected ? 'connected' : 'unavailable',
                'redis' => $redisConnected,
                'timestamp' => now()->toIso8601String(),
            ],
        ], $healthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    private function databaseIsReachable(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Redis is optional in local/testing (CACHE_STORE=array there — see
     * phpunit.xml), so "not configured" is a distinct, non-degraded state
     * from "configured but unreachable". Only the latter marks the overall
     * status degraded.
     */
    private function redisIsReachable(): string
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis' && config('session.driver') !== 'redis') {
            return 'not_configured';
        }

        try {
            Redis::connection()->ping();

            return 'connected';
        } catch (\Throwable) {
            return 'unavailable';
        }
    }
}
