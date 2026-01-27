<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantCouldNotBeIdentifiedByTeamException;
use App\Models\Team;
use App\Models\User;
use App\Resolvers\TeamTenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Initialize tenancy based on the authenticated user's current team.
 *
 * This middleware should be applied AFTER authentication middleware.
 * It identifies the tenant from the user's currentTeam and initializes
 * the tenancy context for the request.
 *
 * Security features:
 * - Verifies user membership in the requested team
 * - Logs all tenancy initialization events for audit
 * - Rate limits tenancy lookups per user
 * - Explicit failure response (no silent pass-through by default)
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

    /**
     * Whether to allow requests without tenant context (default: false for security).
     */
    public static bool $allowWithoutTenant = false;

    /**
     * Rate limit: max attempts per minute for tenant lookups.
     */
    public static int $rateLimitAttempts = 60;

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

        // Type assertion for PHPStan
        if (! $user instanceof User) {
            return $next($request);
        }

        // Get the user's current team
        $team = $user->currentTeam;

        if (! $team instanceof Team) {
            // User has no current team - can happen during registration
            $this->logTenancyEvent('no_team', $user, null, 'User has no current team');

            return $next($request);
        }

        // SECURITY: Verify user actually belongs to this team
        if (! $this->verifyTeamMembership($user, $team)) {
            $this->logTenancyEvent('unauthorized_team_access', $user, $team, 'User attempted to access team they do not belong to');

            return $this->unauthorizedResponse($request);
        }

        // Rate limiting to prevent brute force attacks
        $rateLimitKey = "tenant_lookup:{$user->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, static::$rateLimitAttempts)) {
            $this->logTenancyEvent('rate_limited', $user, $team, 'Rate limit exceeded for tenant lookups');

            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'error'   => 'rate_limit_exceeded',
            ], 429);
        }
        RateLimiter::hit($rateLimitKey, 60); // 60 seconds decay

        try {
            // Initialize tenancy using the team ID
            $tenant = $this->resolver->resolve($team->id);
            $this->tenancy->initialize($tenant);

            $this->logTenancyEvent('initialized', $user, $team, 'Tenancy initialized successfully', [
                'tenant_id' => $tenant->getTenantKey(),
            ]);
        } catch (TenantCouldNotBeIdentifiedByTeamException $e) {
            $this->logTenancyEvent('tenant_not_found', $user, $team, 'No tenant found for team');

            // Handle failure - either throw or use custom handler
            $onFail = static::$onFail;

            if ($onFail !== null) {
                $result = $onFail($e, $request, $next);

                if ($result !== null) {
                    return $result;
                }
            }

            // Default behavior: return 403 unless explicitly allowed
            if (! static::$allowWithoutTenant) {
                return $this->tenantRequiredResponse($request);
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

    /**
     * Verify that the user belongs to the specified team.
     *
     * This is a CRITICAL security check to prevent unauthorized
     * access to other teams' tenant contexts.
     */
    protected function verifyTeamMembership(User $user, Team $team): bool
    {
        // User owns the team
        if ($user->ownsTeam($team)) {
            return true;
        }

        // User is a member of the team
        if ($user->belongsToTeam($team)) {
            return true;
        }

        return false;
    }

    /**
     * Log a tenancy-related event for audit purposes.
     *
     * @param array<string, mixed> $context
     */
    protected function logTenancyEvent(
        string $event,
        User $user,
        ?Team $team,
        string $message,
        array $context = []
    ): void {
        $logContext = array_merge([
            'event'      => "tenancy.{$event}",
            'user_id'    => $user->id,
            'user_email' => $user->email,
            'team_id'    => $team?->id,
            'team_name'  => $team?->name,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url'        => request()->fullUrl(),
        ], $context);

        match ($event) {
            'unauthorized_team_access', 'rate_limited' => Log::warning("[Tenancy] {$message}", $logContext),
            'tenant_not_found' => Log::info("[Tenancy] {$message}", $logContext),
            default            => Log::debug("[Tenancy] {$message}", $logContext),
        };
    }

    /**
     * Return a 403 response for unauthorized team access.
     */
    protected function unauthorizedResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'You do not have access to this team.',
                'error'   => 'unauthorized_team_access',
            ], 403);
        }

        abort(403, 'You do not have access to this team.');
    }

    /**
     * Return a 403 response when tenant context is required but not found.
     */
    protected function tenantRequiredResponse(Request $request): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'A valid tenant context is required for this request.',
                'error'   => 'tenant_context_required',
            ], 403);
        }

        abort(403, 'A valid tenant context is required for this request.');
    }
}
