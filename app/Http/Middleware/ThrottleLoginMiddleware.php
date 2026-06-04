<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dual-axis brute-force throttle: identical 5-failures / 15-minute budgets
 * applied per (tenant, email) AND per (tenant, ip). Either exhausting alone
 * is enough to block — that closes both the password-spray (one email, many
 * passwords) and the credential-stuffing (one IP, many emails) shapes.
 *
 * The middleware only checks budgets; failures are recorded by
 * AuthenticationServiceImpl on every invalid attempt via RateLimiter::hit,
 * and the same keys are cleared on a successful authentication.
 */
class ThrottleLoginMiddleware
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 900; // 15 minutes

    public function handle(Request $request, Closure $next): Response
    {
        $bound = function_exists('tenant') ? tenant() : null;
        $tenantId = $bound !== null ? (string) $bound->getKey() : '';
        $email = (string) $request->input('email', '');
        $ip = (string) $request->ip();

        if ($tenantId === '') {
            return $next($request);
        }

        $emailKey = self::emailKey($tenantId, $email);
        $ipKey = self::ipKey($tenantId, $ip);

        foreach ([$emailKey, $ipKey] as $key) {
            if ($key === null) {
                continue;
            }

            if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
                $retryAfter = RateLimiter::availableIn($key);

                return new JsonResponse([
                    'error' => [
                        'code' => 'too_many_login_attempts',
                        'message' => 'Too many failed login attempts. Please try again later.',
                        'retry_after_seconds' => $retryAfter,
                    ],
                ], Response::HTTP_TOO_MANY_REQUESTS, [
                    'Retry-After' => (string) $retryAfter,
                ]);
            }
        }

        return $next($request);
    }

    public static function emailKey(string $tenantId, string $email): ?string
    {
        if ($email === '') {
            return null;
        }

        return 'login:tenant:'.$tenantId.':email:'.strtolower($email);
    }

    public static function ipKey(string $tenantId, string $ip): ?string
    {
        if ($ip === '') {
            return null;
        }

        return 'login:tenant:'.$tenantId.':ip:'.$ip;
    }
}
