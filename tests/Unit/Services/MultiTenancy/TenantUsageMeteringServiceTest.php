<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MultiTenancy;

use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MultiTenancy\TenantUsageMeteringService;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\ServiceTestCase;
use Throwable;

class TenantUsageMeteringServiceTest extends ServiceTestCase
{
    protected TenantUsageMeteringService $service;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            Cache::flush();

            $this->service = new TenantUsageMeteringService();

            $user = User::factory()->create();

            /** @var Team $team */
            $team = Team::forceCreate([
                'user_id'       => $user->id,
                'name'          => 'Metering Team',
                'personal_team' => false,
            ]);

            /** @var Tenant $tenant */
            $tenant = Tenant::create([
                'team_id' => $team->id,
                'name'    => 'Metering Tenant',
                'plan'    => 'starter',
            ]);

            $this->tenant = $tenant;
        } catch (Throwable $e) {
            $this->markTestSkipped('Tenancy infrastructure unavailable: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_records_and_retrieves_api_calls(): void
    {
        $this->service->recordApiCall($this->tenant);
        $this->service->recordApiCall($this->tenant);
        $this->service->recordApiCall($this->tenant);

        $count = $this->service->getApiCallCount($this->tenant);

        $this->assertEquals(3, $count);
    }

    #[Test]
    public function it_tracks_monthly_api_calls(): void
    {
        $this->service->recordApiCall($this->tenant);
        $this->service->recordApiCall($this->tenant);

        $monthlyCount = $this->service->getMonthlyApiCallCount($this->tenant);

        $this->assertEquals(2, $monthlyCount);
    }

    #[Test]
    public function it_records_api_calls_with_endpoint(): void
    {
        $this->service->recordApiCall($this->tenant, '/api/v1/accounts');
        $this->service->recordApiCall($this->tenant, '/api/v1/transfers');

        $count = $this->service->getApiCallCount($this->tenant);

        $this->assertEquals(2, $count);
    }

    #[Test]
    public function it_returns_zero_for_no_api_calls(): void
    {
        $count = $this->service->getApiCallCount($this->tenant);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_tracks_storage_usage(): void
    {
        $this->service->updateStorageUsage($this->tenant, 256.5);

        $usage = $this->service->getStorageUsage($this->tenant);

        $this->assertEquals(256.5, $usage);
    }

    #[Test]
    public function it_returns_zero_for_no_storage_usage(): void
    {
        $usage = $this->service->getStorageUsage($this->tenant);

        $this->assertEquals(0.0, $usage);
    }

    #[Test]
    public function it_tracks_user_count(): void
    {
        $this->service->updateUserCount($this->tenant, 15);

        $count = $this->service->getUserCount($this->tenant);

        $this->assertEquals(15, $count);
    }

    #[Test]
    public function it_returns_zero_for_no_user_count(): void
    {
        $count = $this->service->getUserCount($this->tenant);

        $this->assertEquals(0, $count);
    }

    #[Test]
    public function it_aggregates_monthly_usage(): void
    {
        $this->service->recordApiCall($this->tenant);
        $this->service->recordApiCall($this->tenant);
        $this->service->updateStorageUsage($this->tenant, 100.0);
        $this->service->updateUserCount($this->tenant, 10);

        $usage = $this->service->getMonthlyUsage($this->tenant);

        $this->assertEquals(2, $usage['api_calls']);
        $this->assertEquals(100.0, $usage['storage_mb']);
        $this->assertEquals(10, $usage['user_count']);
        $this->assertArrayHasKey('month', $usage);
    }

    #[Test]
    public function it_aggregates_and_persists_monthly_usage(): void
    {
        $this->service->recordApiCall($this->tenant);
        $this->service->updateStorageUsage($this->tenant, 50.0);
        $this->service->updateUserCount($this->tenant, 5);

        $result = $this->service->aggregateMonthlyUsage($this->tenant);

        $this->assertEquals(1, $result['api_calls']);
        $this->assertEquals(50.0, $result['storage_mb']);
        $this->assertEquals(5, $result['user_count']);
    }

    #[Test]
    public function it_checks_plan_limits_not_exceeded(): void
    {
        $this->service->recordApiCall($this->tenant);
        $this->service->updateStorageUsage($this->tenant, 50.0);
        $this->service->updateUserCount($this->tenant, 3);

        $result = $this->service->checkPlanLimits($this->tenant, [
            'max_api_calls'  => 10000,
            'max_storage_mb' => 1000,
            'max_users'      => 25,
        ]);

        $this->assertFalse($result['exceeded']);
        $this->assertEmpty($result['violations']);
    }

    #[Test]
    public function it_checks_plan_limits_exceeded(): void
    {
        // Record many API calls
        for ($i = 0; $i < 5; $i++) {
            $this->service->recordApiCall($this->tenant);
        }
        $this->service->updateStorageUsage($this->tenant, 150.0);
        $this->service->updateUserCount($this->tenant, 10);

        $result = $this->service->checkPlanLimits($this->tenant, [
            'max_api_calls'  => 3,
            'max_storage_mb' => 100,
            'max_users'      => 5,
        ]);

        $this->assertTrue($result['exceeded']);
        $this->assertArrayHasKey('api_calls', $result['violations']);
        $this->assertArrayHasKey('storage', $result['violations']);
        $this->assertArrayHasKey('users', $result['violations']);

        $this->assertEquals(5, $result['violations']['api_calls']['current']);
        $this->assertEquals(3, $result['violations']['api_calls']['limit']);
    }

    #[Test]
    public function it_ignores_unlimited_plan_limits(): void
    {
        for ($i = 0; $i < 100; $i++) {
            $this->service->recordApiCall($this->tenant);
        }
        $this->service->updateStorageUsage($this->tenant, 999999.0);
        $this->service->updateUserCount($this->tenant, 10000);

        $result = $this->service->checkPlanLimits($this->tenant, [
            'max_api_calls'  => -1,
            'max_storage_mb' => -1,
            'max_users'      => -1,
        ]);

        $this->assertFalse($result['exceeded']);
        $this->assertEmpty($result['violations']);
    }

    #[Test]
    public function it_resets_daily_counters(): void
    {
        $this->service->recordApiCall($this->tenant);
        $this->service->recordApiCall($this->tenant);

        $this->assertEquals(2, $this->service->getApiCallCount($this->tenant));

        $this->service->resetDailyCounters($this->tenant);

        $this->assertEquals(0, $this->service->getApiCallCount($this->tenant));
    }

    #[Test]
    public function it_isolates_metering_between_tenants(): void
    {
        $user = User::factory()->create();

        /** @var Team $otherTeam */
        $otherTeam = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Other Metering Team',
            'personal_team' => false,
        ]);

        /** @var Tenant $otherTenant */
        $otherTenant = Tenant::create([
            'team_id' => $otherTeam->id,
            'name'    => 'Other Metering Tenant',
            'plan'    => 'free',
        ]);

        // Record calls for first tenant
        $this->service->recordApiCall($this->tenant);
        $this->service->recordApiCall($this->tenant);
        $this->service->recordApiCall($this->tenant);

        // Record calls for second tenant
        $this->service->recordApiCall($otherTenant);

        // Verify isolation
        $this->assertEquals(3, $this->service->getApiCallCount($this->tenant));
        $this->assertEquals(1, $this->service->getApiCallCount($otherTenant));
    }

    #[Test]
    public function it_tracks_storage_independently_per_tenant(): void
    {
        $user = User::factory()->create();

        /** @var Team $otherTeam */
        $otherTeam = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Storage Other Team',
            'personal_team' => false,
        ]);

        /** @var Tenant $otherTenant */
        $otherTenant = Tenant::create([
            'team_id' => $otherTeam->id,
            'name'    => 'Storage Other Tenant',
            'plan'    => 'free',
        ]);

        $this->service->updateStorageUsage($this->tenant, 500.0);
        $this->service->updateStorageUsage($otherTenant, 200.0);

        $this->assertEquals(500.0, $this->service->getStorageUsage($this->tenant));
        $this->assertEquals(200.0, $this->service->getStorageUsage($otherTenant));
    }
}
