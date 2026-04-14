<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Automatically enforces API token scopes based on the HTTP method.
 *
 * GET/HEAD/OPTIONS → requires 'read' scope
 * POST/PUT/PATCH   → requires 'write' scope
 * DELETE           → requires 'delete' scope
 *
 * Tokens created without explicit scopes (i.e. with ['*']) pass all checks.
 * Unauthenticated requests and web-session users are allowed through.
 */
class EnforceMethodScope
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if no authenticated user or no API token (web session)
        if (! $request->user() || ! $request->user()->currentAccessToken()) {
            return $next($request);
        }

        // Skip for TransientToken (used in testing with actingAs)
        if ($request->user()->currentAccessToken() instanceof \Laravel\Sanctum\TransientToken) {
            return $next($request);
        }

        $requiredScope = $this->getScopeForMethod($request->method());

        if ($request->user()->tokenCan($requiredScope)) {
            return $next($request);
        }

        return response()->json([
            'message'        => "Insufficient permissions. Required scope: {$requiredScope}",
            'error'          => 'INSUFFICIENT_SCOPE',
            'required_scope' => $requiredScope,
        ], 403);
    }

    private function getScopeForMethod(string $method): string
    {
        return match (strtoupper($method)) {
            'DELETE'               => 'delete',
            'POST', 'PUT', 'PATCH' => 'write',
            default                => 'read',
        };
    }
}
