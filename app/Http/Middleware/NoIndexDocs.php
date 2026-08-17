<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marks the generated API docs (/docs, /docs.openapi, /docs.postman) as
 * not-for-search-indexing, without requiring authentication. The docs are
 * intentionally public so the Web and Mobile teams can reach them without a
 * session; this just keeps them out of search engines.
 */
class NoIndexDocs
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
