<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the Prometheus metrics endpoints, which expose business metrics
 * (user counts, transaction volumes) that must not be public in production.
 *
 * Access is granted when either:
 *  - the request carries a bearer token matching config('monitoring.metrics_token'), or
 *  - the request IP is in config('monitoring.metrics_allowed_ips').
 *
 * When neither a token nor an allowlist is configured, the gate fails closed
 * in production (403) and stays open in non-production environments.
 */
class MetricsAccessMiddleware
{
    /**
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) config('monitoring.metrics_token');

        if ($token !== '' && hash_equals($token, (string) $request->bearerToken())) {
            return $next($request);
        }

        $allowedIps = config('monitoring.metrics_allowed_ips', []);
        $allowedIps = is_array($allowedIps) ? $allowedIps : [];

        if ($allowedIps !== [] && in_array((string) $request->ip(), $allowedIps, true)) {
            return $next($request);
        }

        // Nothing configured: dev convenience outside production, fail closed in production.
        if ($token === '' && $allowedIps === [] && ! app()->environment('production')) {
            return $next($request);
        }

        return response()->json([
            'error'   => 'Forbidden',
            'message' => 'Metrics access requires a valid bearer token or an allowlisted IP.',
        ], Response::HTTP_FORBIDDEN);
    }
}
