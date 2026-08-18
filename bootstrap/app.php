<?php

use App\Domains\Competency\Exceptions\CompetencyNotEmptyException;
use App\Domains\ExamEngine\Exceptions\InvalidExamStateException;
use App\Domains\Identity\Exceptions\InvalidInviteTokenException;
use App\Domains\Identity\Exceptions\PasswordPolicyViolationException;
use App\Domains\QuestionBank\Exceptions\CategoryNotEmptyException;
use App\Http\Middleware\EnsureCentralAdmin;
use App\Http\Middleware\EnsureIdempotency;
use App\Http\Middleware\NoIndexDocs;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\ThrottleLoginMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'throttle.login' => ThrottleLoginMiddleware::class,
            'central.admin' => EnsureCentralAdmin::class,
            'idempotent' => EnsureIdempotency::class,
            'noindex.docs' => NoIndexDocs::class,
        ]);

        // Baseline response security headers on every request. Additive only —
        // see SecurityHeaders' own docblock for why HSTS is conditional.
        $middleware->append(SecurityHeaders::class);

        // Trusts NO proxies by default (empty array => $request->ip() reflects
        // the real TCP peer, identical to not registering this middleware at
        // all — zero behavior change today). A production deployment sitting
        // behind a real load balancer/reverse proxy must set TRUSTED_PROXIES
        // (comma-separated IPs/CIDRs) so $request->ip() and scheme detection
        // reflect the real client rather than the proxy. Deliberately not
        // guessing real proxy IP ranges here — see docs/SECURITY_BASELINE.md.
        $middleware->trustProxies(at: array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        ))));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InvalidExamStateException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'invalid_exam_state',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (InvalidInviteTokenException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'invalid_invite_token',
                    'message' => $e->getMessage(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (PasswordPolicyViolationException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'password_policy_violation',
                    'message' => $e->getMessage(),
                    'violations' => $e->violations,
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (CategoryNotEmptyException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'category_not_empty',
                    'message' => $e->getMessage(),
                    'has_children' => $e->hasChildren,
                    'has_questions' => $e->hasQuestions,
                ],
            ], Response::HTTP_CONFLICT);
        });

        $exceptions->render(function (CompetencyNotEmptyException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'competency_not_empty',
                    'message' => $e->getMessage(),
                    'has_children' => $e->hasChildren,
                    'has_questions' => $e->hasQuestions,
                ],
            ], Response::HTTP_CONFLICT);
        });

        // ---------------------------------------------------------------
        // Phase 5 — Public API Foundation: generic fallbacks.
        //
        // Everything above handles a specific domain exception with extra
        // payload fields. These generic renders below are the safety net —
        // they guarantee every API response (not just the ones a controller
        // remembered to catch) uses the same {"error": {"code","message"}}
        // envelope, instead of Laravel's default HTML/plain error shape.
        // Registered last so the specific ones above still win when they
        // apply; render callbacks are tried in registration order and the
        // first non-null wins.
        // ---------------------------------------------------------------

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'validation_failed',
                    'message' => $e->getMessage(),
                    'fields' => $e->errors(),
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'not_authenticated',
                    'message' => 'Authentication required.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        });

        $exceptions->render(function (AuthorizationException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => $e->getMessage() ?: 'You do not have permission to perform this action.',
                ],
            ], Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'The requested resource was not found.',
                ],
            ], Response::HTTP_NOT_FOUND);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'The requested resource was not found.',
                ],
            ], Response::HTTP_NOT_FOUND);
        });

        // Rate limiting — registered ahead of the generic HttpExceptionInterface
        // catch-all below so it wins for this specific exception type. Preserves
        // Retry-After (and any X-RateLimit-* headers the limiter set) instead of
        // losing them the way the generic handler would.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'rate_limited',
                    'message' => 'Too many requests. Please slow down and try again shortly.',
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS, $e->getHeaders());
        });

        // Any other HTTP-flavored exception (method not allowed, etc.) —
        // preserve its real status code but normalize the body shape.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'http_error',
                    'message' => $e->getMessage() ?: 'An error occurred.',
                ],
            ], $e->getStatusCode());
        });

        // Last resort — an unhandled Throwable. In production this hides
        // internals behind a generic message; in debug mode it still lets
        // Laravel's default detailed handler take over.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->expectsJson() && ! $request->is('api/*')) {
                return null;
            }

            if (config('app.debug')) {
                return null;
            }

            return response()->json([
                'error' => [
                    'code' => 'internal_error',
                    'message' => 'An unexpected error occurred.',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    })->create();
