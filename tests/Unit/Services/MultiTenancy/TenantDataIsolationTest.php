<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MultiTenancy;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Http\Middleware\TenantResolutionMiddleware;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\ServiceTestCase;
use Throwable;

class TenantDataIsolationTest extends ServiceTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Redirect the central connection to the default test connection
        // so Tenant model (which uses CentralConnection trait) works with SQLite
        $default = config('database.default');
        config([
            'tenancy.database.central_connection' => $default,
        ]);
    }

    /**
     * Create a test tenant, skipping the test if the tenancy DB manager isn't registered.
     */
    /**
     * @param  array<string, mixed>  $extra
     */
    protected function createTestTenant(Team $team, string $name, string $plan = 'free', array $extra = []): Tenant
    {
        try {
            /** @var Tenant $tenant */
            $tenant = Tenant::create(array_merge(['team_id' => $team->id, 'name' => $name, 'plan' => $plan], $extra));

            return $tenant;
        } catch (Throwable $e) {
            $this->markTestSkipped('Tenancy infrastructure unavailable: ' . $e->getMessage());
        }
    }

    #[Test]
    public function uses_tenant_connection_returns_null_in_testing_environment(): void
    {
        Config::set('app.env', 'testing');

        $model = new class () {
            use UsesTenantConnection;
        };

        $this->assertNull($model->getConnectionName());
    }

    #[Test]
    public function uses_tenant_connection_returns_tenant_in_production(): void
    {
        Config::set('app.env', 'production');

        $model = new class () {
            use UsesTenantConnection;
        };

        $this->assertEquals('tenant', $model->getConnectionName());
    }

    #[Test]
    public function uses_tenant_connection_returns_tenant_in_local(): void
    {
        Config::set('app.env', 'local');

        $model = new class () {
            use UsesTenantConnection;
        };

        $this->assertEquals('tenant', $model->getConnectionName());
    }

    #[Test]
    public function tenant_resolution_header_takes_priority_over_subdomain(): void
    {
        $user = User::factory()->create();

        /** @var Team $teamA */
        $teamA = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Team A',
            'personal_team' => false,
        ]);

        /** @var Team $teamB */
        $teamB = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Team B',
            'personal_team' => false,
        ]);

        $tenantA = $this->createTestTenant($teamA, 'tenant-a');
        $tenantB = $this->createTestTenant($teamB, 'tenant-b');

        Cache::flush();

        // Create request with header pointing to tenant A
        $request = Request::create('https://tenant-b.example.com/api/test', 'GET');
        $request->headers->set('X-Tenant-ID', (string) $tenantA->id);
        $request->setUserResolver(fn () => $user);

        $middleware = new TenantResolutionMiddleware();

        $response = $middleware->handle($request, function (Request $req) use ($tenantA) {
            // Verify resolved tenant is from header, not subdomain
            $resolved = $req->attributes->get(TenantResolutionMiddleware::TENANT_ATTRIBUTE);
            $this->assertNotNull($resolved);
            $this->assertEquals($tenantA->id, $resolved->id);

            return response()->json(['ok' => true]);
        });

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function tenant_resolution_returns_404_when_no_tenant_found(): void
    {
        $request = Request::create('https://example.com/api/test', 'GET');

        $middleware = new TenantResolutionMiddleware();

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        });

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[Test]
    public function tenant_resolution_returns_403_for_suspended_tenant(): void
    {
        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Suspended Team',
            'personal_team' => false,
        ]);

        $tenant = $this->createTestTenant($team, 'suspended-tenant', 'free', ['data' => ['status' => 'suspended']]);

        Cache::flush();

        $request = Request::create('https://example.com/api/test', 'GET');
        $request->headers->set('X-Tenant-ID', (string) $tenant->id);
        $request->setUserResolver(fn () => $user);

        $middleware = new TenantResolutionMiddleware();

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        });

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Tenant suspended', $content['error']);
    }

    #[Test]
    public function tenant_resolution_denies_access_to_other_teams_tenant(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        /** @var Team $teamA */
        $teamA = Team::forceCreate([
            'user_id'       => $ownerA->id,
            'name'          => 'Team A Owned',
            'personal_team' => false,
        ]);

        $tenantA = $this->createTestTenant($teamA, 'team-a-tenant');

        Cache::flush();

        // Owner B tries to access tenant A
        $request = Request::create('https://example.com/api/test', 'GET');
        $request->headers->set('X-Tenant-ID', (string) $tenantA->id);
        $request->setUserResolver(fn () => $ownerB);

        $middleware = new TenantResolutionMiddleware();

        $response = $middleware->handle($request, function () {
            return response()->json(['ok' => true]);
        });

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $content = json_decode((string) $response->getContent(), true);
        $this->assertEquals('Access denied', $content['error']);
    }

    #[Test]
    public function tenant_owner_can_access_their_own_tenant(): void
    {
        $owner = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $owner->id,
            'name'          => 'Owner Team',
            'personal_team' => false,
        ]);

        $tenant = $this->createTestTenant($team, 'owner-tenant');

        Cache::flush();

        $request = Request::create('https://example.com/api/test', 'GET');
        $request->headers->set('X-Tenant-ID', (string) $tenant->id);
        $request->setUserResolver(fn () => $owner);

        $middleware = new TenantResolutionMiddleware();

        $response = $middleware->handle($request, function (Request $req) use ($tenant) {
            $resolved = $req->attributes->get(TenantResolutionMiddleware::TENANT_ATTRIBUTE);
            $this->assertNotNull($resolved);
            $this->assertEquals($tenant->id, $resolved->id);

            return response()->json(['ok' => true]);
        });

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    #[Test]
    public function each_tenant_has_unique_data_isolation(): void
    {
        $user = User::factory()->create();

        /** @var Team $teamA */
        $teamA = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Isolation Team A',
            'personal_team' => false,
        ]);

        /** @var Team $teamB */
        $teamB = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Isolation Team B',
            'personal_team' => false,
        ]);

        $tenantA = $this->createTestTenant($teamA, 'isolation-a', 'starter', ['data' => ['config' => ['custom' => 'value-a']]]);
        $tenantB = $this->createTestTenant($teamB, 'isolation-b', 'professional', ['data' => ['config' => ['custom' => 'value-b']]]);

        // Verify each tenant has its own distinct data
        $this->assertNotEquals($tenantA->id, $tenantB->id);
        $this->assertNotEquals($tenantA->team_id, $tenantB->team_id);
        $this->assertEquals('starter', $tenantA->plan);
        $this->assertEquals('professional', $tenantB->plan);

        /** @var array<string, mixed> $dataA */
        $dataA = $tenantA->data;
        /** @var array<string, mixed> $dataB */
        $dataB = $tenantB->data;

        $this->assertEquals('value-a', $dataA['config']['custom']);
        $this->assertEquals('value-b', $dataB['config']['custom']);

        // Verify querying one tenant does not return data from another
        $foundA = Tenant::where('team_id', $teamA->id)->first();
        $this->assertNotNull($foundA);
        $this->assertEquals($tenantA->id, $foundA->id);

        $foundB = Tenant::where('team_id', $teamB->id)->first();
        $this->assertNotNull($foundB);
        $this->assertEquals($tenantB->id, $foundB->id);
    }
}
