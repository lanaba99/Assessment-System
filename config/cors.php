<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS) Configuration
|--------------------------------------------------------------------------
|
| This app has no config/cors.php by default, which means Laravel's
| HandleCors middleware resolves an empty paths list and never adds any
| Access-Control-Allow-* header — silently blocking any browser-based
| frontend (the Web team's SPA) from making authenticated cross-origin
| requests. This file fixes that without ever using a wildcard origin for
| an API that accepts bearer tokens: allowed origins come from
| CORS_ALLOWED_ORIGINS (comma-separated) and default to empty (nothing
| allowed) rather than guessing a real frontend URL.
|
| supports_credentials stays false: this API is bearer-token authenticated
| (Authorization header), not cookie/session-based, so browsers never need
| to send credentials mode for it — turning this on for a wildcard-free but
| still browser-supplied origin list is unnecessary risk with no benefit
| here. See docs/SECURITY_BASELINE.md for the full explanation.
|
*/

return [

    'paths' => ['api/*'],

    'allowed_methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', '')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'X-Requested-With',
        'Idempotency-Key',
        'X-Tenant-ID',
    ],

    'exposed_headers' => [
        'Retry-After',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-Idempotent-Replay',
    ],

    'max_age' => 3600,

    'supports_credentials' => false,

];
