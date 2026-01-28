<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Team;
use App\Models\Tenant;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for initializing tenant context in Filament admin panel.
 *
 * This middleware checks if the authenticated user has selected a team/tenant
 * and initializes the tenant context for the request. This allows Filament
 * resources to use TenantAwareResource trait for automatic data filtering.
 *
 * Usage in AdminPanelProvider:
 * ```php
 * ->middleware([
 *     // ... other middleware
 *     FilamentTenantMiddleware::class,
 * ])
 * ```
 */
class FilamentTenantMiddleware
{
    /**
     * Session key for storing the selected tenant.
     */
    public const TENANT_SESSION_KEY = 'filament_tenant_id';

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip if tenancy package is not available
        if (! class_exists(Tenancy::class)) {
            return $next($request);
        }

        // Skip if no authenticated user
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        // Check if user wants to switch tenant (via query parameter)
        $requestedTenantId = $request->query('tenant');
        if ($requestedTenantId && is_string($requestedTenantId)) {
            $this->switchTenant($request, $user, $requestedTenantId);
        }

        // Get tenant ID from session or user's current team
        $tenantId = $this->resolveTenantId($request, $user);

        // Initialize tenant context if we have a valid tenant
        if ($tenantId) {
            $this->initializeTenant($tenantId);
        }

        return $next($request);
    }

    /**
     * Switch to a different tenant.
     *
     * @param object $user The authenticated user
     */
    protected function switchTenant(Request $request, object $user, string $tenantId): void
    {
        // Verify user has access to this tenant
        $tenant = Tenant::find($tenantId);
        if (! $tenant || ! $this->userCanAccessTenant($user, $tenant)) {
            Log::warning('Filament tenant switch denied', [
                'user_id'   => $user->id,
                'tenant_id' => $tenantId,
            ]);

            return;
        }

        // Store in session
        $request->session()->put(self::TENANT_SESSION_KEY, $tenantId);

        Log::info('Filament tenant switched', [
            'user_id'   => $user->id,
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Resolve the tenant ID for the current request.
     *
     * @param object $user The authenticated user
     */
    protected function resolveTenantId(Request $request, object $user): ?string
    {
        // First, check session for previously selected tenant
        $sessionTenantId = $request->session()->get(self::TENANT_SESSION_KEY);
        if ($sessionTenantId) {
            // Verify user still has access
            /** @var Tenant|null $tenant */
            $tenant = Tenant::find($sessionTenantId);
            if ($tenant instanceof Tenant && $this->userCanAccessTenant($user, $tenant)) {
                return $sessionTenantId;
            }

            // Clear invalid session tenant
            $request->session()->forget(self::TENANT_SESSION_KEY);
        }

        // Fall back to user's current team
        if (method_exists($user, 'currentTeam') && $user->currentTeam) {
            $team = $user->currentTeam;
            $tenant = Tenant::where('team_id', $team->id)->first();
            if ($tenant) {
                return (string) $tenant->id;
            }
        }

        // Check if user is platform admin (can see all tenants)
        if ($this->isPlatformAdmin($user)) {
            // Platform admin - no tenant context (sees all)
            return null;
        }

        // Try to get first accessible tenant for regular users
        $tenant = $this->getFirstAccessibleTenant($user);

        return $tenant?->id;
    }

    /**
     * Initialize the tenant context.
     */
    protected function initializeTenant(string $tenantId): void
    {
        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            return;
        }

        try {
            tenancy()->initialize($tenant);

            Log::debug('Filament tenant context initialized', [
                'tenant_id' => $tenantId,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to initialize Filament tenant context', [
                'tenant_id' => $tenantId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if a user can access a specific tenant.
     *
     * @param object $user The authenticated user
     */
    protected function userCanAccessTenant(object $user, Tenant $tenant): bool
    {
        // Platform admins can access any tenant
        if ($this->isPlatformAdmin($user)) {
            return true;
        }

        // Check if tenant is linked to a team the user belongs to
        if ($tenant->team_id === null) {
            return false;
        }

        $team = Team::find($tenant->team_id);
        if (! $team) {
            return false;
        }

        // Check if user is owner or member
        if ($team->user_id === $user->id) {
            return true;
        }

        if (method_exists($team, 'hasUser')) {
            /** @phpstan-ignore argument.type */
            return $team->hasUser($user);
        }

        return false;
    }

    /**
     * Check if the user is a platform administrator.
     *
     * Platform admins can access all tenants and see global data.
     *
     * @param object $user The authenticated user
     */
    protected function isPlatformAdmin(object $user): bool
    {
        // Check for platform_admin role or permission
        if (method_exists($user, 'hasRole') && $user->hasRole('platform_admin')) {
            return true;
        }

        if (method_exists($user, 'hasPermission') && $user->hasPermission('access_all_tenants')) {
            return true;
        }

        return false;
    }

    /**
     * Get the first tenant the user can access.
     *
     * @param object $user The authenticated user
     */
    protected function getFirstAccessibleTenant(object $user): ?Tenant
    {
        // Get user's teams
        if (! method_exists($user, 'allTeams')) {
            return null;
        }

        $teamIds = $user->allTeams()->pluck('id');

        return Tenant::whereIn('team_id', $teamIds)->first();
    }
}
