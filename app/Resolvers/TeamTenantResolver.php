<?php

declare(strict_types=1);

namespace App\Resolvers;

use App\Exceptions\TenantCouldNotBeIdentifiedByTeamException;
use App\Models\Tenant;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Resolvers\Contracts\CachedTenantResolver;

/**
 * Resolves a tenant based on the team_id.
 *
 * This resolver looks up a tenant by its associated team.
 * If auto-creation is enabled, it will create a tenant for
 * teams that don't have one yet.
 */
class TeamTenantResolver extends CachedTenantResolver
{
    /** @var bool Whether to cache resolved tenants */
    public static $shouldCache = true;

    /** @var int Cache TTL in seconds */
    public static $cacheTTL = 3600;

    /** @var string|null Cache store to use */
    public static $cacheStore = null;

    /** @var bool Whether to auto-create tenants for teams without one */
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
            throw new TenantCouldNotBeIdentifiedByTeamException();
        }

        $tenant = Tenant::where('team_id', $teamId)->first();

        if ($tenant) {
            return $tenant;
        }

        // Optionally auto-create tenant for the team
        if (static::$autoCreateTenant) {
            $team = \App\Models\Team::query()->find($teamId);
            if ($team instanceof \App\Models\Team) {
                return Tenant::createFromTeam($team);
            }
        }

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
}
