<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides a demo-only route in production: it answers 404 as if the route did
 * not exist. Other environments (demo, local, testing, staging) reach it.
 * Used on every api/demo/* route.
 */
final class DenyInProduction
{
    public function __construct(
        private readonly Application $app,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->app->environment('production')) {
            abort(404);
        }

        return $next($request);
    }
}
