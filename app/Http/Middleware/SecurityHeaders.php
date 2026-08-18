<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline response security headers to every request. Purely
 * additive — never touches the response body, status, or any endpoint's
 * payload/business logic. Registered globally in bootstrap/app.php.
 *
 * Strict-Transport-Security is only ever added when the request itself
 * arrived over HTTPS ($request->secure()) — sending HSTS on a plain-HTTP
 * local/Sail request would tell the browser to force HTTPS for future
 * requests to that host, which would break local development the next time
 * someone visits http://*.localhost. This is not the same as "only in
 * production": a correctly TLS-terminated production request is secure()
 * regardless of environment, and a local request never is, so the check is
 * exactly the safe condition.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
