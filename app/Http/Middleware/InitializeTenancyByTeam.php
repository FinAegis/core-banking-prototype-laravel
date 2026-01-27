<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantCouldNotBeIdentifiedByTeamException;
use App\Resolvers\TeamTenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initialize tenancy based on the authenticated user's current team.
 *
 * This middleware should be applied AFTER authentication middleware.
 * It identifies the tenant from the user's currentTeam and initializes
 * the tenancy context for the request.
 *
 * Usage in routes:
 * ```php
 * Route::middleware(['auth', 'tenant'])->group(function () {
 *     // Tenant-aware routes
 * });
 * ```
 */
class InitializeTenancyByTeam
{
    /**
     * Callback to execute when tenant identification fails.
     *
     * @var callable|null
     */
    public static $onFail;

    public function __construct(
        protected Tenancy $tenancy,
        protected TeamTenantResolver $resolver
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip OPTIONS requests (CORS preflight)
        if ($request->method() === 'OPTIONS') {
            return $next($request);
        }

        // Get the authenticated user
        $user = Auth::user();

        if (! $user) {
            // No authenticated user - skip tenant initialization
            // The auth middleware should handle unauthorized requests
            return $next($request);
        }

        // Get the user's current team
        $team = $user->currentTeam;

        if (! $team) {
            // User has no current team - can happen during registration
            return $next($request);
        }

        try {
            // Initialize tenancy using the team ID
            $this->tenancy->initialize(
                $this->resolver->resolve($team->id)
            );
        } catch (TenantCouldNotBeIdentifiedByTeamException $e) {
            // Handle failure - either throw or use custom handler
            $onFail = static::$onFail ?? function (TenantCouldNotBeIdentifiedByTeamException $e) {
                // By default, allow request to continue without tenant context
                // This is useful during initial setup or for central routes
                return null;
            };

            $result = $onFail($e, $request, $next);

            if ($result !== null) {
                return $result;
            }
        }

        return $next($request);
    }

    /**
     * Terminate the middleware.
     *
     * Clean up tenancy context after the request is complete.
     */
    public function terminate(Request $request, Response $response): void
    {
        if ($this->tenancy->initialized) {
            $this->tenancy->end();
        }
    }
}
