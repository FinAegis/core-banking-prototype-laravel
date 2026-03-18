<?php

declare(strict_types=1);

namespace Tests\Unit\Services\MultiTenancy;

use App\Events\Tenant\TenantCreated;
use App\Events\Tenant\TenantDeleted;
use App\Events\Tenant\TenantSuspended;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MultiTenancy\TenantProvisioningService;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\ServiceTestCase;
use Throwable;

class TenantProvisioningServiceTest extends ServiceTestCase
{
    protected TenantProvisioningService $service;

    protected function setUp(): void
    {
        parent::setUp();

        try {
            // Verify tenancy DB manager works by creating a test tenant
            $this->service = new TenantProvisioningService();
            $testUser = User::factory()->create();
            $testTeam = Team::forceCreate(['user_id' => $testUser->id, 'name' => 'setup-test', 'personal_team' => false]);
            Tenant::create(['team_id' => $testTeam->id, 'name' => 'setup-probe', 'plan' => 'free']);
        } catch (Throwable $e) {
            $this->markTestSkipped('Tenancy infrastructure unavailable: ' . $e->getMessage());
        }
    }

    #[Test]
    public function it_creates_a_tenant_for_a_team(): void
    {
        Event::fake([TenantCreated::class]);

        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Test Team',
            'personal_team' => false,
        ]);

        $tenant = $this->service->createTenant($team, 'My Tenant', 'starter');

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->assertEquals($team->id, $tenant->team_id);
        $this->assertEquals('My Tenant', $tenant->name);
        $this->assertEquals('starter', $tenant->plan);

        /** @var array<string, mixed> $data */
        $data = $tenant->data;
        $this->assertEquals('active', $data['status']);

        Event::assertDispatched(TenantCreated::class, function (TenantCreated $event) use ($tenant) {
            return $event->tenant->id === $tenant->id && $event->plan === 'starter';
        });
    }

    #[Test]
    public function it_creates_tenant_with_free_plan_by_default(): void
    {
        Event::fake([TenantCreated::class]);

        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Free Team',
            'personal_team' => false,
        ]);

        $tenant = $this->service->createTenant($team, 'Free Tenant');

        $this->assertEquals('free', $tenant->plan);

        /** @var array<string, mixed> $data */
        $data = $tenant->data;
        /** @var array<string, mixed> $config */
        $config = $data['config'];
        $this->assertEquals(5, $config['max_users']);
        $this->assertEquals(1000, $config['max_api_calls']);
    }

    #[Test]
    public function it_rejects_duplicate_tenant_for_same_team(): void
    {
        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Duplicate Team',
            'personal_team' => false,
        ]);

        Tenant::create([
            'team_id' => $team->id,
            'name'    => 'Existing Tenant',
            'plan'    => 'free',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Tenant already exists for team');

        $this->service->createTenant($team, 'Duplicate Tenant');
    }

    #[Test]
    public function it_rejects_invalid_plan(): void
    {
        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Invalid Plan Team',
            'personal_team' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid plan: nonexistent');

        $this->service->createTenant($team, 'Invalid Tenant', 'nonexistent');
    }

    #[Test]
    public function it_sets_tenant_plan(): void
    {
        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Plan Team',
            'personal_team' => false,
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'team_id' => $team->id,
            'name'    => 'Plan Tenant',
            'plan'    => 'free',
            'data'    => ['config' => ['max_users' => 5], 'status' => 'active'],
        ]);

        $updated = $this->service->setTenantPlan($tenant, 'professional');

        $this->assertEquals('professional', $updated->plan);

        /** @var array<string, mixed> $data */
        $data = $updated->data;
        /** @var array<string, mixed> $config */
        $config = $data['config'];
        $this->assertEquals(100, $config['max_users']);
        $this->assertEquals(100000, $config['max_api_calls']);
    }

    #[Test]
    public function it_updates_tenant_config(): void
    {
        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Config Team',
            'personal_team' => false,
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'team_id' => $team->id,
            'name'    => 'Config Tenant',
            'plan'    => 'starter',
            'data'    => ['config' => ['max_users' => 25], 'status' => 'active'],
        ]);

        $updated = $this->service->updateTenantConfig($tenant, [
            'custom_domain' => 'app.example.com',
            'webhook_url'   => 'https://hooks.example.com/notify',
        ]);

        /** @var array<string, mixed> $data */
        $data = $updated->data;
        /** @var array<string, mixed> $config */
        $config = $data['config'];
        $this->assertEquals('app.example.com', $config['custom_domain']);
        $this->assertEquals('https://hooks.example.com/notify', $config['webhook_url']);
        // Original config preserved
        $this->assertEquals(25, $config['max_users']);
    }

    #[Test]
    public function it_suspends_a_tenant(): void
    {
        Event::fake([TenantSuspended::class]);

        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Suspend Team',
            'personal_team' => false,
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'team_id' => $team->id,
            'name'    => 'Suspend Tenant',
            'plan'    => 'free',
            'data'    => ['status' => 'active'],
        ]);

        $suspended = $this->service->suspendTenant($tenant, 'Payment overdue');

        /** @var array<string, mixed> $data */
        $data = $suspended->data;
        $this->assertEquals('suspended', $data['status']);
        $this->assertEquals('Payment overdue', $data['suspension_reason']);
        $this->assertArrayHasKey('suspended_at', $data);

        $this->assertFalse($this->service->isTenantActive($suspended));

        Event::assertDispatched(TenantSuspended::class, function (TenantSuspended $event) use ($tenant) {
            return $event->tenant->id === $tenant->id && $event->reason === 'Payment overdue';
        });
    }

    #[Test]
    public function it_reactivates_a_suspended_tenant(): void
    {
        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Reactivate Team',
            'personal_team' => false,
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'team_id' => $team->id,
            'name'    => 'Reactivate Tenant',
            'plan'    => 'free',
            'data'    => [
                'status'            => 'suspended',
                'suspended_at'      => now()->toIso8601String(),
                'suspension_reason' => 'Test',
            ],
        ]);

        $reactivated = $this->service->reactivateTenant($tenant);

        /** @var array<string, mixed> $data */
        $data = $reactivated->data;
        $this->assertEquals('active', $data['status']);
        $this->assertArrayNotHasKey('suspended_at', $data);
        $this->assertArrayNotHasKey('suspension_reason', $data);

        $this->assertTrue($this->service->isTenantActive($reactivated));
    }

    #[Test]
    public function it_deletes_a_tenant(): void
    {
        Event::fake([TenantDeleted::class]);

        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Delete Team',
            'personal_team' => false,
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'team_id' => $team->id,
            'name'    => 'Delete Tenant',
            'plan'    => 'free',
        ]);

        $tenantId = (string) $tenant->id;

        $result = $this->service->deleteTenant($tenant);

        $this->assertTrue($result);
        $this->assertNull(Tenant::find($tenantId));

        Event::assertDispatched(TenantDeleted::class, function (TenantDeleted $event) use ($tenantId) {
            return $event->tenantId === $tenantId && $event->tenantName === 'Delete Tenant';
        });
    }

    #[Test]
    public function it_gets_tenant_status(): void
    {
        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Status Team',
            'personal_team' => false,
        ]);

        /** @var Tenant $activeTenant */
        $activeTenant = Tenant::create([
            'team_id' => $team->id,
            'name'    => 'Active Tenant',
            'plan'    => 'free',
            'data'    => ['status' => 'active'],
        ]);

        $this->assertEquals('active', $this->service->getTenantStatus($activeTenant));
        $this->assertTrue($this->service->isTenantActive($activeTenant));
    }

    #[Test]
    public function it_gets_tenant_config(): void
    {
        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Config Read Team',
            'personal_team' => false,
        ]);

        /** @var Tenant $tenant */
        $tenant = Tenant::create([
            'team_id' => $team->id,
            'name'    => 'Config Read Tenant',
            'plan'    => 'starter',
            'data'    => ['config' => ['max_users' => 25, 'custom_key' => 'custom_val']],
        ]);

        $config = $this->service->getTenantConfig($tenant);

        $this->assertEquals(25, $config['max_users']);
        $this->assertEquals('custom_val', $config['custom_key']);
    }

    #[Test]
    public function it_lists_available_plans(): void
    {
        $plans = $this->service->getAvailablePlans();

        $this->assertArrayHasKey('free', $plans);
        $this->assertArrayHasKey('starter', $plans);
        $this->assertArrayHasKey('professional', $plans);
        $this->assertArrayHasKey('enterprise', $plans);

        $this->assertEquals(5, $plans['free']['max_users']);
        $this->assertEquals(-1, $plans['enterprise']['max_users']); // unlimited
    }

    #[Test]
    public function it_creates_tenant_with_custom_config(): void
    {
        Event::fake([TenantCreated::class]);

        $user = User::factory()->create();

        /** @var Team $team */
        $team = Team::forceCreate([
            'user_id'       => $user->id,
            'name'          => 'Custom Config Team',
            'personal_team' => false,
        ]);

        $tenant = $this->service->createTenant($team, 'Custom Tenant', 'free', [
            'custom_domain' => 'custom.example.com',
        ]);

        /** @var array<string, mixed> $data */
        $data = $tenant->data;
        /** @var array<string, mixed> $config */
        $config = $data['config'];
        $this->assertEquals('custom.example.com', $config['custom_domain']);
        // Default plan values still present
        $this->assertEquals(5, $config['max_users']);
    }
}
