<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for auto-resolving tenant context from incoming API requests.
 *
 * Resolution priority (highest to lowest):
 * 1. X-Tenant-ID header
 * 2. Subdomain
 * 3. Bearer token user's current team
 *
 * Caches resolved tenant for the request lifecycle to avoid redundant lookups.
 * Works alongside FilamentTenantMiddleware (which handles Filament admin panel).
 */
class TenantResolutionMiddleware
{
    /**
     * Cache TTL in seconds for tenant resolution lookups.
     */
    private const TENANT_CACHE_TTL = 300;

    /**
     * Request attribute key for storing resolved tenant.
     */
    public const TENANT_ATTRIBUTE = 'resolved_tenant';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveTenant($request);

        if ($tenant === null) {
            Log::debug('Tenant resolution failed — no tenant could be resolved', [
                'path'   => $request->path(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'error'   => 'Not found',
                'message' => 'The requested resource could not be found.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check tenant status
        /** @var array<string, mixed> $data */
        $data = $tenant->data ?? [];
        $status = (string) ($data['status'] ?? 'active');

        if ($status === 'suspended') {
            return response()->json([
                'error'   => 'Tenant suspended',
                'message' => 'This tenant has been suspended. Contact support for assistance.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Verify authenticated user has access to this tenant
        $user = $request->user();
        if ($user !== null && ! $this->userCanAccessTenant($user, $tenant)) {
            Log::warning('Tenant access denied — user lacks access', [
                'user_id'   => $user->id,
                'tenant_id' => $tenant->id,
            ]);

            return response()->json([
                'error'   => 'Access denied',
                'message' => 'You do not have access to this tenant.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Store resolved tenant in request attributes for downstream use
        $request->attributes->set(self::TENANT_ATTRIBUTE, $tenant);

        return $next($request);
    }

    /**
     * Resolve tenant using the priority chain: header > subdomain > token.
     */
    protected function resolveTenant(Request $request): ?Tenant
    {
        // Priority 1: X-Tenant-ID header
        $tenant = $this->resolveFromHeader($request);
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        // Priority 2: Subdomain
        $tenant = $this->resolveFromSubdomain($request);
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        // Priority 3: Authenticated user's current team
        $tenant = $this->resolveFromToken($request);
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        return null;
    }

    /**
     * Resolve tenant from the X-Tenant-ID header.
     */
    protected function resolveFromHeader(Request $request): ?Tenant
    {
        $tenantId = $request->header('X-Tenant-ID');
        if ($tenantId === null || $tenantId === '') {
            return null;
        }

        return $this->findTenantCached($tenantId);
    }

    /**
     * Resolve tenant from the request subdomain.
     */
    protected function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $parts = explode('.', $host);

        // Need at least 3 parts for a subdomain (e.g., tenant.example.com)
        if (count($parts) < 3) {
            return null;
        }

        $subdomain = $parts[0];

        // Skip common non-tenant subdomains
        if (in_array($subdomain, ['www', 'api', 'admin', 'app', 'localhost'], true)) {
            return null;
        }

        $cacheKey = 'tenant_subdomain:' . $subdomain;

        return Cache::remember($cacheKey, self::TENANT_CACHE_TTL, function () use ($subdomain): ?Tenant {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::where('name', $subdomain)->first();

            return $tenant;
        });
    }

    /**
     * Resolve tenant from the authenticated user's current team.
     */
    protected function resolveFromToken(Request $request): ?Tenant
    {
        $user = $request->user();
        if ($user === null) {
            return null;
        }

        // Use user's current team to find the tenant
        if (method_exists($user, 'currentTeam') && $user->currentTeam !== null) {
            $teamId = $user->currentTeam->id;
            $cacheKey = 'tenant_team:' . $teamId;

            return Cache::remember($cacheKey, self::TENANT_CACHE_TTL, function () use ($teamId): ?Tenant {
                /** @var Tenant|null $tenant */
                $tenant = Tenant::where('team_id', $teamId)->first();

                return $tenant;
            });
        }

        return null;
    }

    /**
     * Find a tenant by ID with caching.
     */
    protected function findTenantCached(string $tenantId): ?Tenant
    {
        $cacheKey = 'tenant_id:' . $tenantId;

        return Cache::remember($cacheKey, self::TENANT_CACHE_TTL, function () use ($tenantId): ?Tenant {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::find($tenantId);

            return $tenant;
        });
    }

    /**
     * Check if a user can access a specific tenant.
     *
     * @param object $user The authenticated user
     */
    protected function userCanAccessTenant(object $user, Tenant $tenant): bool
    {
        // Platform admins can access any tenant
        if (method_exists($user, 'hasRole') && $user->hasRole('platform_admin')) {
            return true;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission('access_all_tenants')) {
            return true;
        }

        // Check if tenant is linked to a team the user belongs to
        if ($tenant->team_id === null) {
            return false;
        }

        $team = Team::find($tenant->team_id);
        if (! $team instanceof Team) {
            return false;
        }

        // Check if user is owner
        if ($team->user_id === $user->id) {
            return true;
        }

        // Check if user is a member
        /** @phpstan-ignore argument.type */
        return $team->hasUser($user);
    }
}
