<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Exceptions\TenantCouldNotBeIdentifiedByTeamException;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Resolvers\TeamTenantResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stancl\Tenancy\Tenancy;
use Tests\CreatesApplication;

/**
 * Unit tests for TeamTenantResolver.
 */
class TeamTenantResolverTest extends BaseTestCase
{
    use CreatesApplication;
    use LazilyRefreshDatabase;

    protected TeamTenantResolver $resolver;

    /**
     * Define environment setup - called before setUp().
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('permission.cache.store', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(TeamTenantResolver::class);
    }

    protected function tearDown(): void
    {
        TeamTenantResolver::$autoCreateTenant = false;

        // End tenancy if still active
        $tenancy = app(Tenancy::class);
        if ($tenancy->initialized) {
            $tenancy->end();
        }

        parent::tearDown();
    }

    public function test_resolver_can_be_instantiated(): void
    {
        $this->assertInstanceOf(TeamTenantResolver::class, $this->resolver);
    }

    public function test_resolver_resolves_tenant_by_team_id(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tenant = Tenant::createFromTeam($team);

        $resolved = $this->resolver->resolve($team->id);

        $this->assertInstanceOf(Tenant::class, $resolved);
        $this->assertEquals($tenant->id, $resolved->id);
        $this->assertEquals($team->id, $resolved->team_id);
    }

    public function test_resolver_throws_exception_when_team_id_is_null(): void
    {
        $this->expectException(TenantCouldNotBeIdentifiedByTeamException::class);
        $this->expectExceptionMessage('No team context available');

        $this->resolver->resolve(null);
    }

    public function test_resolver_throws_exception_when_tenant_not_found(): void
    {
        $this->expectException(TenantCouldNotBeIdentifiedByTeamException::class);
        $this->expectExceptionMessage('team ID: 99999');

        $this->resolver->resolve(99999);
    }

    public function test_resolver_caches_resolved_tenants(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        Tenant::createFromTeam($team);

        // First resolution
        $firstResolve = $this->resolver->resolve($team->id);

        // Second resolution (should be cached)
        $secondResolve = $this->resolver->resolve($team->id);

        $this->assertInstanceOf(Tenant::class, $firstResolve);
        $this->assertInstanceOf(Tenant::class, $secondResolve);
        /** @var Tenant $firstResolve */
        /** @var Tenant $secondResolve */
        $this->assertEquals($firstResolve->id, $secondResolve->id);
    }

    public function test_resolver_returns_cache_arguments_for_tenant(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tenant = Tenant::createFromTeam($team);

        $args = $this->resolver->getArgsForTenant($tenant);

        $this->assertIsArray($args);
        $this->assertCount(1, $args);
        $this->assertEquals([$team->id], $args[0]);
    }

    public function test_resolver_static_cache_settings(): void
    {
        $this->assertTrue(TeamTenantResolver::$shouldCache);
        $this->assertEquals(3600, TeamTenantResolver::$cacheTTL);
        $this->assertNull(TeamTenantResolver::$cacheStore);
    }

    public function test_resolver_auto_create_disabled_by_default(): void
    {
        $this->assertFalse(TeamTenantResolver::$autoCreateTenant);
    }

    public function test_resolver_auto_creates_tenant_when_enabled(): void
    {
        TeamTenantResolver::$autoCreateTenant = true;

        try {
            $user = User::factory()->create();
            $team = Team::factory()->create([
                'user_id' => $user->id,
                'name'    => 'Auto Created Team',
            ]);

            // No tenant exists yet
            $this->assertNull(Tenant::where('team_id', $team->id)->first());

            // Resolve should auto-create
            $resolved = $this->resolver->resolve($team->id);

            $this->assertInstanceOf(Tenant::class, $resolved);
            $this->assertEquals($team->id, $resolved->team_id);
            $this->assertEquals('Auto Created Team', $resolved->name);
        } finally {
            TeamTenantResolver::$autoCreateTenant = false;
        }
    }

    public function test_resolver_throws_when_team_not_found_for_auto_create(): void
    {
        TeamTenantResolver::$autoCreateTenant = true;

        try {
            $this->expectException(TenantCouldNotBeIdentifiedByTeamException::class);
            $this->resolver->resolve(99999);
        } finally {
            TeamTenantResolver::$autoCreateTenant = false;
        }
    }
}
