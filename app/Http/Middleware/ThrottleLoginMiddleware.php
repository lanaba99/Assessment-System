<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Identity\Repositories\LoginAttemptRepository;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ThrottleLoginMiddleware
{
    private const MAX_FAILURES = 5;

    private const WINDOW_MINUTES = 15;

    public function __construct(
        private readonly LoginAttemptRepository $loginAttempts,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Tenant context is established by InitializeTenancyBySubdomain on the route
        // group; if tenancy isn't initialized (e.g. central request), skip throttling here.
        $bound = function_exists('tenant') ? tenant() : null;
        $tenantId = $bound !== null ? (string) $bound->getKey() : '';
        $email = (string) $request->input('email', '');

        if ($tenantId === '' || $email === '') {
            // Let validation handle malformed payloads; do not throttle pre-validation noise.
            return $next($request);
        }

        $recentFailures = $this->loginAttempts->countRecentFailuresForEmail(
            $tenantId,
            $email,
            self::WINDOW_MINUTES,
        );

        if ($recentFailures >= self::MAX_FAILURES) {
            return new JsonResponse([
                'error' => [
                    'code' => 'too_many_login_attempts',
                    'message' => 'Too many failed login attempts. Please try again later.',
                    'retry_after_minutes' => self::WINDOW_MINUTES,
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) (self::WINDOW_MINUTES * 60),
            ]);
        }

        return $next($request);
    }
}
