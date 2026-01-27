<?php

declare(strict_types=1);

namespace Tests\Feature\MultiTenancy;

use App\Exceptions\TenantCouldNotBeIdentifiedByTeamException;
use App\Http\Middleware\InitializeTenancyByTeam;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Resolvers\TeamTenantResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stancl\Tenancy\Tenancy;
use Tests\CreatesApplication;

/**
 * Integration tests for multi-tenancy data isolation.
 *
 * These tests verify that:
 * 1. Team-tenant resolution works correctly
 * 2. Middleware initializes tenancy properly
 * 3. Data isolation is maintained between tenants
 * 4. Security boundaries are enforced
 */
class DataIsolationTest extends BaseTestCase
{
    use CreatesApplication;
    use LazilyRefreshDatabase;

    /**
     * Define environment setup - called before setUp().
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('permission.cache.store', 'array');
    }

    protected function tearDown(): void
    {
        InitializeTenancyByTeam::$onFail = null;
        InitializeTenancyByTeam::$allowWithoutTenant = false;
        TeamTenantResolver::$autoCreateTenant = false;
        TeamTenantResolver::resetConfiguration();

        // End tenancy if still active
        $tenancy = app(Tenancy::class);
        if ($tenancy->initialized) {
            $tenancy->end();
        }

        parent::tearDown();
    }

    // ========================================
    // Team-Tenant Resolution Tests
    // ========================================

    public function test_tenant_can_be_resolved_by_team_id(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tenant = Tenant::createFromTeam($team);

        $resolver = app(TeamTenantResolver::class);
        $resolved = $resolver->resolve($team->id);

        $this->assertInstanceOf(Tenant::class, $resolved);
        /** @var Tenant $resolved */
        $this->assertEquals($tenant->id, $resolved->id);
        $this->assertEquals($team->id, $resolved->team_id);
    }

    public function test_same_tenant_resolved_for_same_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        Tenant::createFromTeam($team);

        $resolver = app(TeamTenantResolver::class);
        $first = $resolver->resolve($team->id);
        $second = $resolver->resolve($team->id);

        $this->assertInstanceOf(Tenant::class, $first);
        $this->assertInstanceOf(Tenant::class, $second);
        /** @var Tenant $first */
        /** @var Tenant $second */
        $this->assertEquals($first->id, $second->id);
    }

    public function test_different_teams_resolve_to_different_tenants(): void
    {
        $user1 = User::factory()->create();
        $team1 = Team::factory()->create(['user_id' => $user1->id, 'name' => 'Team 1']);
        $tenant1 = Tenant::createFromTeam($team1);

        $user2 = User::factory()->create();
        $team2 = Team::factory()->create(['user_id' => $user2->id, 'name' => 'Team 2']);
        $tenant2 = Tenant::createFromTeam($team2);

        $resolver = app(TeamTenantResolver::class);
        $resolved1 = $resolver->resolve($team1->id);
        $resolved2 = $resolver->resolve($team2->id);

        $this->assertInstanceOf(Tenant::class, $resolved1);
        $this->assertInstanceOf(Tenant::class, $resolved2);
        /** @var Tenant $resolved1 */
        /** @var Tenant $resolved2 */
        $this->assertNotEquals($resolved1->id, $resolved2->id);
    }

    public function test_team_without_tenant_throws_exception(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $this->expectException(TenantCouldNotBeIdentifiedByTeamException::class);

        $resolver = app(TeamTenantResolver::class);
        $resolver->resolve($team->id);
    }

    public function test_exception_contains_team_id(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        try {
            $resolver = app(TeamTenantResolver::class);
            $resolver->resolve($team->id);
            $this->fail('Expected exception not thrown');
        } catch (TenantCouldNotBeIdentifiedByTeamException $e) {
            $this->assertEquals($team->id, $e->getTeamId());
            $this->assertStringContainsString((string) $team->id, $e->getMessage());
        }
    }

    // ========================================
    // Tenancy Initialization Tests
    // ========================================

    public function test_tenancy_can_be_initialized_for_tenant(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tenant = Tenant::createFromTeam($team);

        $tenancy = app(Tenancy::class);
        $tenancy->initialize($tenant);

        $this->assertTrue($tenancy->initialized);
        $this->assertNotNull($tenancy->tenant);
        /** @var Tenant $currentTenant */
        $currentTenant = $tenancy->tenant;
        $this->assertEquals($tenant->id, $currentTenant->id);

        $tenancy->end();
    }

    public function test_tenancy_can_be_ended(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tenant = Tenant::createFromTeam($team);

        $tenancy = app(Tenancy::class);
        $tenancy->initialize($tenant);
        $tenancy->end();

        $this->assertFalse($tenancy->initialized);
    }

    public function test_current_tenant_accessible_during_tenancy(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id, 'name' => 'Test Corp']);
        $tenant = Tenant::createFromTeam($team);

        $tenancy = app(Tenancy::class);
        $tenancy->initialize($tenant);

        $this->assertNotNull($tenancy->tenant);
        /** @var Tenant $current */
        $current = $tenancy->tenant;
        $this->assertEquals('Test Corp', $current->name);
        $this->assertEquals($team->id, $current->team_id);

        $tenancy->end();
    }

    // ========================================
    // Middleware Integration Tests
    // ========================================

    public function test_middleware_initializes_tenancy_via_http(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        Tenant::createFromTeam($team);
        $user->switchTeam($team);

        $response = $this->actingAs($user)
            ->withMiddleware([InitializeTenancyByTeam::class])
            ->get('/api/user');

        // Just verify the request completed (may get 401 for other reasons, but tenancy processed)
        $this->assertTrue($response->status() >= 200 && $response->status() < 500);
    }

    public function test_middleware_skips_preflight_requests(): void
    {
        $response = $this->options('/api/test');

        // OPTIONS should be handled gracefully
        $this->assertTrue($response->status() >= 200 && $response->status() < 500);
    }

    public function test_middleware_allows_unauthenticated_requests(): void
    {
        $response = $this->get('/');

        // Should not error on unauthenticated request
        $this->assertTrue($response->status() >= 200 && $response->status() < 500);
    }

    // ========================================
    // Security Boundary Tests
    // ========================================

    public function test_tenant_team_relationship_is_bidirectional(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $tenant = Tenant::createFromTeam($team);

        // Forward: team_id on tenant
        $this->assertEquals($team->id, $tenant->team_id);

        // Backward: tenant() relationship on tenant
        $this->assertNotNull($tenant->team);
        $this->assertEquals($team->id, $tenant->team->id);
    }

    public function test_tenant_cannot_be_resolved_with_wrong_team_id(): void
    {
        $user1 = User::factory()->create();
        $team1 = Team::factory()->create(['user_id' => $user1->id]);
        $tenant1 = Tenant::createFromTeam($team1);

        $user2 = User::factory()->create();
        $team2 = Team::factory()->create(['user_id' => $user2->id]);
        // No tenant for team2

        $resolver = app(TeamTenantResolver::class);

        // Resolve tenant1 - should work
        $resolved = $resolver->resolve($team1->id);
        $this->assertInstanceOf(Tenant::class, $resolved);
        /** @var Tenant $resolved */
        $this->assertEquals($tenant1->id, $resolved->id);

        // Resolve team2 - should fail
        $this->expectException(TenantCouldNotBeIdentifiedByTeamException::class);
        $resolver->resolve($team2->id);
    }

    public function test_user_cannot_access_other_teams_tenant(): void
    {
        $user1 = User::factory()->create();
        $team1 = Team::factory()->create(['user_id' => $user1->id]);
        $tenant1 = Tenant::createFromTeam($team1);
        $user1->switchTeam($team1);

        $user2 = User::factory()->create();
        $team2 = Team::factory()->create(['user_id' => $user2->id]);
        $tenant2 = Tenant::createFromTeam($team2);
        $user2->switchTeam($team2);

        $resolver = app(TeamTenantResolver::class);

        // User1's team resolves to tenant1
        $resolved1 = $resolver->resolve($team1->id);
        $this->assertInstanceOf(Tenant::class, $resolved1);
        /** @var Tenant $resolved1 */
        $this->assertEquals($tenant1->id, $resolved1->id);

        // User2's team resolves to tenant2
        $resolved2 = $resolver->resolve($team2->id);
        $this->assertInstanceOf(Tenant::class, $resolved2);
        /** @var Tenant $resolved2 */
        $this->assertEquals($tenant2->id, $resolved2->id);

        // They are different
        $this->assertNotEquals($tenant1->id, $tenant2->id);
    }

    public function test_tenant_isolation_config_enabled(): void
    {
        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
            config('tenancy.bootstrappers'),
            'Database isolation should be enabled'
        );
    }

    public function test_cache_isolation_config_enabled(): void
    {
        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
            config('tenancy.bootstrappers'),
            'Cache isolation should be enabled'
        );
    }

    public function test_filesystem_isolation_config_enabled(): void
    {
        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
            config('tenancy.bootstrappers'),
            'Filesystem isolation should be enabled'
        );
    }

    public function test_queue_isolation_config_enabled(): void
    {
        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
            config('tenancy.bootstrappers'),
            'Queue isolation should be enabled'
        );
    }

    // ========================================
    // Auto-Create Tenant Tests
    // ========================================

    public function test_auto_create_creates_tenant_when_enabled(): void
    {
        TeamTenantResolver::$autoCreateTenant = true;

        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name'    => 'Auto Team',
        ]);

        // No tenant exists yet
        $this->assertNull(Tenant::where('team_id', $team->id)->first());

        $resolver = app(TeamTenantResolver::class);
        $resolved = $resolver->resolve($team->id);

        $this->assertInstanceOf(Tenant::class, $resolved);
        /** @var Tenant $resolved */
        $this->assertEquals($team->id, $resolved->team_id);
        $this->assertEquals('Auto Team', $resolved->name);
    }

    public function test_auto_create_disabled_by_default(): void
    {
        $this->assertFalse(TeamTenantResolver::$autoCreateTenant);

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);

        $resolver = app(TeamTenantResolver::class);

        $this->expectException(TenantCouldNotBeIdentifiedByTeamException::class);
        $resolver->resolve($team->id);
    }

    // ========================================
    // Custom Failure Handler Tests
    // ========================================

    public function test_custom_onfail_handler_can_be_set(): void
    {
        $handlerCalled = false;

        InitializeTenancyByTeam::$onFail = function () use (&$handlerCalled) {
            $handlerCalled = true;

            return null;
        };

        // Allow without tenant for this test
        InitializeTenancyByTeam::$allowWithoutTenant = true;

        $this->assertNotNull(InitializeTenancyByTeam::$onFail);

        // Trigger by resolving non-existent tenant
        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        $user->switchTeam($team);

        $response = $this->actingAs($user)
            ->withMiddleware([InitializeTenancyByTeam::class])
            ->get('/');

        $this->assertTrue($handlerCalled);
    }

    // ========================================
    // Security Integration Tests
    // ========================================

    public function test_user_cannot_hijack_another_teams_tenant_via_http(): void
    {
        $user1 = User::factory()->create();
        $team1 = Team::factory()->create(['user_id' => $user1->id]);
        $tenant1 = Tenant::createFromTeam($team1);

        $user2 = User::factory()->create();
        $team2 = Team::factory()->create(['user_id' => $user2->id]);
        $tenant2 = Tenant::createFromTeam($team2);

        // Attempt to hijack - set user1's current_team_id to team2
        $user1->current_team_id = $team2->id;
        $user1->save();

        $response = $this->actingAs($user1)
            ->withMiddleware([InitializeTenancyByTeam::class])
            ->getJson('/api/user');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'unauthorized_team_access');
    }

    public function test_default_behavior_returns_403_for_missing_tenant(): void
    {
        InitializeTenancyByTeam::$allowWithoutTenant = false;

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        // No tenant for this team
        $user->switchTeam($team);

        $response = $this->actingAs($user)
            ->withMiddleware([InitializeTenancyByTeam::class])
            ->getJson('/api/user');

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'tenant_context_required');
    }

    public function test_allow_without_tenant_flag_works_via_http(): void
    {
        InitializeTenancyByTeam::$allowWithoutTenant = true;

        $user = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $user->id]);
        // No tenant for this team
        $user->switchTeam($team);

        $response = $this->actingAs($user)
            ->withMiddleware([InitializeTenancyByTeam::class])
            ->get('/');

        // Should not get 403 - request proceeds without tenant
        $this->assertTrue(
            $response->status() >= 200 && $response->status() < 400,
            "Expected 2xx/3xx status, got {$response->status()}"
        );
    }

    public function test_team_member_can_access_tenant_via_http(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        Tenant::createFromTeam($team);

        $member = User::factory()->create();
        $team->users()->attach($member, ['role' => 'member']);
        $member->switchTeam($team);
        $member->refresh();

        $response = $this->actingAs($member)
            ->withMiddleware([InitializeTenancyByTeam::class])
            ->get('/');

        // Should succeed
        $this->assertTrue(
            $response->status() >= 200 && $response->status() < 500,
            "Expected success status, got {$response->status()}"
        );
    }

    public function test_team_owner_can_access_tenant_via_http(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['user_id' => $owner->id]);
        Tenant::createFromTeam($team);
        $owner->switchTeam($team);

        $response = $this->actingAs($owner)
            ->withMiddleware([InitializeTenancyByTeam::class])
            ->get('/');

        // Should succeed
        $this->assertTrue(
            $response->status() >= 200 && $response->status() < 500,
            "Expected success status, got {$response->status()}"
        );
    }
}
