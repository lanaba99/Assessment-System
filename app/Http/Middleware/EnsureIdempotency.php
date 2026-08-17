<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opt-in idempotency for unsafe (POST/PATCH/DELETE) endpoints.
 *
 * Attach via the `idempotent` middleware alias on individual routes where a
 * duplicate submission (double-tap, retried request after a timeout, etc.)
 * would cause a real problem — not applied globally. Endpoints that already
 * have their own DB-level idempotency (e.g. ExamSessionController::start(),
 * which looks up an existing in-progress session before creating a new one)
 * don't need this and aren't tagged with it.
 *
 * Client sends an `Idempotency-Key` header (any client-generated unique
 * string, e.g. a UUID). Behavior:
 *   - No header present            → pass through untouched, no caching.
 *   - Header present, first time   → request executes normally; the response
 *                                     (status < 500) is cached against
 *                                     (tenant, actor, route, key) for 24h.
 *   - Header present, replay,
 *     same request body            → the cached response is replayed
 *                                     verbatim; the handler does NOT run
 *                                     again. Response carries
 *                                     `X-Idempotent-Replay: true`.
 *   - Header present, replay,
 *     different request body       → 409 Conflict (`idempotency_key_reused`)
 *                                     — the key was reused for a materially
 *                                     different request, which is almost
 *                                     always a client bug (key not
 *                                     regenerated per logical operation).
 *   - Handler response is 5xx      → not cached, so a transient server
 *                                     error doesn't get "locked in" for the
 *                                     replay window; the client's next
 *                                     identical retry executes fresh.
 */
class EnsureIdempotency
{
    private const TTL_SECONDS = 86400; // 24 hours

    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if ($idempotencyKey === null || $idempotencyKey === '') {
            return $next($request);
        }

        $cacheKey = $this->cacheKey($request, $idempotencyKey);
        $bodyHash = $this->bodyHash($request);

        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            if ($cached['body_hash'] !== $bodyHash) {
                return new JsonResponse([
                    'error' => [
                        'code' => 'idempotency_key_reused',
                        'message' => 'This Idempotency-Key was already used with a different request body. Use a new key for a new logical request.',
                    ],
                ], Response::HTTP_CONFLICT);
            }

            return new JsonResponse(
                $cached['body'],
                $cached['status'],
                ['X-Idempotent-Replay' => 'true'],
            );
        }

        /** @var Response $response */
        $response = $next($request);

        if ($response->getStatusCode() < 500 && $response instanceof JsonResponse) {
            Cache::put($cacheKey, [
                'body_hash' => $bodyHash,
                'status' => $response->getStatusCode(),
                'body' => json_decode($response->getContent() ?: '{}', true),
            ], self::TTL_SECONDS);
        }

        return $response;
    }

    private function cacheKey(Request $request, string $idempotencyKey): string
    {
        $tenantId = function_exists('tenant') && tenant() !== null
            ? (string) tenant()->getKey()
            : 'no-tenant';

        $actor = $request->user();
        $actorId = $actor !== null ? (string) $actor->id : 'anonymous';

        $route = $request->route()?->getName() ?? $request->path();

        return "idempotency:{$tenantId}:{$actorId}:{$route}:{$idempotencyKey}";
    }

    private function bodyHash(Request $request): string
    {
        return hash('sha256', $request->getContent());
    }
}