<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Central\Models\CentralAdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof CentralAdminUser) {
            return response()->json([
                'error' => [
                    'code' => 'central_auth_required',
                    'message' => 'Central administrator authentication is required.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
