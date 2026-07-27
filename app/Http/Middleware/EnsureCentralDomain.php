<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            abort(404);
        }

        return $next($request);
    }
}