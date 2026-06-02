<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SystemController extends Controller
{
    public function status(): JsonResponse
    {
        $databaseConnected = $this->databaseIsReachable();
        $tenantId = tenant() !== null ? (string) tenant()->getKey() : null;

        return new JsonResponse([
            'data' => [
                'status' => $databaseConnected ? 'ok' : 'degraded',
                'tenant_id' => $tenantId,
                'database' => $databaseConnected ? 'connected' : 'unavailable',
                'environment' => app()->environment(),
                'timestamp' => now()->toIso8601String(),
            ],
        ], $databaseConnected ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
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
}
