<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds RFC 8594 Deprecation and Sunset headers for legacy API endpoints.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc8594
 */
class ApiDeprecationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  string|null  $sunset  ISO 8601 date when endpoint will be removed
     */
    public function handle(Request $request, Closure $next, ?string $sunset = null): Response
    {
        $response = $next($request);

        // RFC 8594: Deprecation header indicates the endpoint is deprecated
        $response->headers->set('Deprecation', 'true');

        // Link to migration guide
        $response->headers->set('Link', '</api/v2>; rel="successor-version"');

        // Sunset header: date when the endpoint will be removed
        if ($sunset !== null) {
            $response->headers->set('Sunset', $sunset);
        }

        return $response;
    }
}
