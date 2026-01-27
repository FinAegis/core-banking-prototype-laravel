<?php

declare(strict_types=1);

namespace App\Resolvers;

use App\Exceptions\TenantCouldNotBeIdentifiedByTeamException;
use App\Models\Team;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Resolvers\Contracts\CachedTenantResolver;

/**
 * Resolves a tenant based on the team_id.
 *
 * This resolver looks up a tenant by its associated team.
 * If auto-creation is enabled (via config), it will create a tenant
 * for teams that don't have one yet.
 *
 * Security features:
 * - Config-based auto-creation (disabled by default)
 * - Cache invalidation support
 * - Audit logging for tenant resolution
 * - No sensitive information in error messages
 */
class TeamTenantResolver extends CachedTenantResolver
{
    /** @var bool Whether to cache resolved tenants */
    public static $shouldCache = true;

    /** @var int Cache TTL in seconds */
    public static $cacheTTL = 3600;

    /** @var string|null Cache store to use */
    public static $cacheStore = null;

    /**
     * Whether to auto-create tenants for teams without one.
     * This should ONLY be enabled in development/demo environments.
     * Production should always have tenants created through proper workflows.
     */
    public static bool $autoCreateTenant = false;

    /**
     * Resolve a tenant by team ID.
     *
     * @param mixed ...$args First argument should be the team_id (int)
     * @return TenantContract
     * @throws TenantCouldNotBeIdentifiedByTeamException
     */
    public function resolveWithoutCache(...$args): TenantContract
    {
        $teamId = $args[0] ?? null;

        if ($teamId === null) {
            Log::debug('[TenantResolver] Resolution attempted without team ID');
            throw new TenantCouldNotBeIdentifiedByTeamException();
        }

        // Validate team ID is a positive integer
        if (! is_int($teamId) || $teamId <= 0) {
            Log::warning('[TenantResolver] Invalid team ID format attempted', [
                'team_id_type' => gettype($teamId),
            ]);
            throw new TenantCouldNotBeIdentifiedByTeamException();
        }

        $tenant = Tenant::where('team_id', $teamId)->first();

        if ($tenant) {
            Log::debug('[TenantResolver] Tenant resolved for team', [
                'team_id'   => $teamId,
                'tenant_id' => $tenant->getTenantKey(),
            ]);

            return $tenant;
        }

        // Check config-based auto-creation (more secure than static property alone)
        $shouldAutoCreate = static::$autoCreateTenant
            || config('multitenancy.auto_create_tenants', false);

        // Auto-creation should be environment-restricted
        $allowedEnvironments = config('multitenancy.auto_create_environments', ['local', 'testing', 'demo']);

        if ($shouldAutoCreate && in_array(app()->environment(), $allowedEnvironments, true)) {
            $team = Team::query()->find($teamId);

            if ($team instanceof Team) {
                Log::info('[TenantResolver] Auto-creating tenant for team', [
                    'team_id'     => $teamId,
                    'team_name'   => $team->name,
                    'environment' => app()->environment(),
                ]);

                $tenant = Tenant::createFromTeam($team);

                return $tenant;
            }
        }

        // Log without exposing sensitive details
        Log::info('[TenantResolver] Tenant not found for team', [
            'team_id' => $teamId,
        ]);

        throw new TenantCouldNotBeIdentifiedByTeamException($teamId);
    }

    /**
     * Get the cache key arguments for a given tenant.
     *
     * @param TenantContract $tenant
     * @return array<int, array<int, mixed>>
     */
    public function getArgsForTenant(TenantContract $tenant): array
    {
        /** @var Tenant $tenant */
        return [
            [$tenant->team_id],
        ];
    }

    /**
     * Invalidate the cached tenant for a specific team.
     *
     * Call this when:
     * - A tenant is deleted
     * - A tenant's team association changes
     * - Team is deleted
     */
    public static function invalidateCacheForTeam(int $teamId): void
    {
        $cacheKey = static::getCacheKeyForTeam($teamId);
        $store = static::$cacheStore ?? config('cache.default');

        Cache::store($store)->forget($cacheKey);

        Log::debug('[TenantResolver] Cache invalidated for team', [
            'team_id' => $teamId,
        ]);
    }

    /**
     * Invalidate the cached tenant.
     */
    public static function invalidateCacheForTenant(Tenant $tenant): void
    {
        if ($tenant->team_id) {
            static::invalidateCacheForTeam($tenant->team_id);
        }
    }

    /**
     * Get the cache key for a team ID.
     */
    protected static function getCacheKeyForTeam(int $teamId): string
    {
        // Match the cache key format used by CachedTenantResolver
        return 'tenancy:resolver:' . static::class . ':' . serialize([$teamId]);
    }

    /**
     * Configure the resolver for a specific environment.
     *
     * Useful for testing or specific deployment scenarios.
     *
     * @param array<string, mixed> $config
     */
    public static function configure(array $config): void
    {
        if (isset($config['cache'])) {
            static::$shouldCache = (bool) $config['cache'];
        }

        if (isset($config['cache_ttl'])) {
            static::$cacheTTL = (int) $config['cache_ttl'];
        }

        if (isset($config['cache_store'])) {
            static::$cacheStore = $config['cache_store'];
        }

        if (isset($config['auto_create'])) {
            static::$autoCreateTenant = (bool) $config['auto_create'];
        }
    }

    /**
     * Reset configuration to defaults.
     */
    public static function resetConfiguration(): void
    {
        static::$shouldCache = true;
        static::$cacheTTL = 3600;
        static::$cacheStore = null;
        static::$autoCreateTenant = false;
    }
}
