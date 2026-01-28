<?php

declare(strict_types=1);

namespace Tests\Security\MultiTenancy;

use App\Broadcasting\TenantBroadcastEvent;
use App\Broadcasting\TenantChannelAuthorizer;
use App\Domain\Shared\Jobs\TenantAwareJob;
use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Filament\Admin\TenantAwareResource;
use App\Models\Tenant;
use App\Services\MultiTenancy\TenantDataMigrationService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Security audit tests for cross-tenant access prevention.
 *
 * These are pure unit tests that verify structural security aspects
 * of tenant-aware components without requiring database or Redis access.
 */
class CrossTenantAccessPreventionTest extends TestCase
{
    #[Test]
    public function uses_tenant_connection_trait_exists(): void
    {
        $this->assertTrue(trait_exists(UsesTenantConnection::class));
    }

    #[Test]
    public function uses_tenant_connection_trait_has_required_methods(): void
    {
        $reflection = new ReflectionClass(UsesTenantConnection::class);

        $this->assertTrue($reflection->hasMethod('getConnectionName'));
    }

    #[Test]
    public function tenant_aware_job_trait_exists(): void
    {
        $this->assertTrue(trait_exists(TenantAwareJob::class));
    }

    #[Test]
    public function tenant_aware_job_trait_has_tenant_tracking(): void
    {
        $reflection = new ReflectionClass(TenantAwareJob::class);

        // Should have property for tenant ID tracking
        $this->assertTrue($reflection->hasProperty('dispatchedTenantId'));
        // Should have initialization method
        $this->assertTrue($reflection->hasMethod('initializeTenantAwareJob'));
        // Should have context verification
        $this->assertTrue($reflection->hasMethod('verifyTenantContext'));
    }

    #[Test]
    public function tenant_aware_resource_trait_exists(): void
    {
        $this->assertTrue(trait_exists(TenantAwareResource::class));
    }

    #[Test]
    public function tenant_aware_resource_has_scope_filtering(): void
    {
        $reflection = new ReflectionClass(TenantAwareResource::class);

        // Should have methods for filtering queries by tenant
        $this->assertTrue($reflection->hasMethod('getEloquentQuery'));
        $this->assertTrue($reflection->hasMethod('applyTenantScope'));
        $this->assertTrue($reflection->hasMethod('hasTenantContext'));
    }

    #[Test]
    public function tenant_broadcast_event_trait_exists(): void
    {
        $this->assertTrue(trait_exists(TenantBroadcastEvent::class));
    }

    #[Test]
    public function tenant_broadcast_event_has_tenant_methods(): void
    {
        $reflection = new ReflectionClass(TenantBroadcastEvent::class);

        // Should have method to get tenant ID for broadcasting
        $this->assertTrue($reflection->hasMethod('getTenantIdForBroadcast'));
        // Should have method for tenant channel suffix
        $this->assertTrue($reflection->hasMethod('tenantChannelSuffix'));
    }

    #[Test]
    public function tenant_channel_authorizer_exists(): void
    {
        $this->assertTrue(class_exists(TenantChannelAuthorizer::class));
    }

    #[Test]
    public function tenant_channel_authorizer_has_authorization_methods(): void
    {
        $reflection = new ReflectionClass(TenantChannelAuthorizer::class);

        // Should have methods for channel authorization
        $this->assertTrue($reflection->hasMethod('authorizeUser'));
        $this->assertTrue($reflection->hasMethod('authorizeAdmin'));
        $this->assertTrue($reflection->hasMethod('userBelongsToTenant'));
    }

    #[Test]
    public function tenant_data_migration_service_exists(): void
    {
        $this->assertTrue(class_exists(TenantDataMigrationService::class));
    }

    #[Test]
    public function tenant_data_migration_service_has_required_methods(): void
    {
        $reflection = new ReflectionClass(TenantDataMigrationService::class);

        $this->assertTrue($reflection->hasMethod('migrateDataForTenant'));
        $this->assertTrue($reflection->hasMethod('getRecordCounts'));
        $this->assertTrue($reflection->hasMethod('isMigrated'));
        $this->assertTrue($reflection->hasMethod('getMigratableTables'));
    }

    #[Test]
    public function tenant_model_extends_stancl_base_tenant(): void
    {
        $reflection = new ReflectionClass(Tenant::class);
        $parent = $reflection->getParentClass();

        $this->assertNotFalse($parent);
        $this->assertEquals('Stancl\Tenancy\Database\Models\Tenant', $parent->getName());
    }

    #[Test]
    public function tenant_model_implements_tenant_with_database(): void
    {
        $reflection = new ReflectionClass(Tenant::class);
        $interfaces = $reflection->getInterfaceNames();

        $this->assertContains(
            'Stancl\Tenancy\Contracts\TenantWithDatabase',
            $interfaces
        );
    }

    #[Test]
    public function tenant_model_has_required_relationships(): void
    {
        $reflection = new ReflectionClass(Tenant::class);

        // Tenant should have team relationship for isolation
        $this->assertTrue($reflection->hasMethod('team'));
    }

    #[Test]
    public function stancl_tenancy_bootstrappers_exist(): void
    {
        // Verify that required bootstrapper classes exist
        $this->assertTrue(class_exists(\Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class));
        $this->assertTrue(class_exists(\Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class));
        $this->assertTrue(class_exists(\Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class));
    }

    #[Test]
    public function tenant_model_has_custom_columns(): void
    {
        $columns = Tenant::getCustomColumns();

        $this->assertIsArray($columns);
        $this->assertContains('team_id', $columns);
        $this->assertContains('name', $columns);
    }
}
