<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use ReflectionClass;
use Tests\CreatesApplication;

/**
 * Unit tests for multi-tenancy setup validation.
 *
 * These tests verify the configuration and class structure
 * without requiring database connections.
 */
class TenancySetupTest extends BaseTestCase
{
    use CreatesApplication;

    public function test_tenant_model_exists(): void
    {
        $this->assertTrue(
            class_exists(Tenant::class),
            'Tenant model should exist'
        );
    }

    public function test_tenant_model_extends_base_tenant(): void
    {
        $reflection = new ReflectionClass(Tenant::class);

        $this->assertTrue(
            $reflection->isSubclassOf(\Stancl\Tenancy\Database\Models\Tenant::class),
            'Tenant model should extend stancl/tenancy base Tenant'
        );
    }

    public function test_tenant_model_implements_tenant_with_database(): void
    {
        $reflection = new ReflectionClass(Tenant::class);

        $this->assertTrue(
            $reflection->implementsInterface(\Stancl\Tenancy\Contracts\TenantWithDatabase::class),
            'Tenant model should implement TenantWithDatabase'
        );
    }

    public function test_tenant_model_uses_has_database_trait(): void
    {
        $traits = class_uses_recursive(Tenant::class);

        $this->assertContains(
            \Stancl\Tenancy\Database\Concerns\HasDatabase::class,
            $traits,
            'Tenant model should use HasDatabase trait'
        );
    }

    public function test_tenant_model_has_custom_columns(): void
    {
        $columns = Tenant::getCustomColumns();

        $this->assertIsArray($columns);
        $this->assertContains('id', $columns);
        $this->assertContains('team_id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('plan', $columns);
        $this->assertContains('trial_ends_at', $columns);
    }

    public function test_tenant_model_has_team_relationship(): void
    {
        $reflection = new ReflectionClass(Tenant::class);

        $this->assertTrue(
            $reflection->hasMethod('team'),
            'Tenant model should have team() relationship method'
        );
    }

    public function test_tenant_model_has_create_from_team_method(): void
    {
        $reflection = new ReflectionClass(Tenant::class);

        $this->assertTrue(
            $reflection->hasMethod('createFromTeam'),
            'Tenant model should have createFromTeam() factory method'
        );
    }

    public function test_tenancy_config_uses_custom_tenant_model(): void
    {
        $this->assertEquals(
            Tenant::class,
            config('tenancy.tenant_model'),
            'Tenancy config should use custom Tenant model'
        );
    }

    public function test_tenancy_config_has_bootstrappers(): void
    {
        $bootstrappers = config('tenancy.bootstrappers');

        $this->assertIsArray($bootstrappers);
        $this->assertNotEmpty($bootstrappers);

        // Verify essential bootstrappers
        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
            $bootstrappers,
            'Database bootstrapper should be configured'
        );

        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
            $bootstrappers,
            'Cache bootstrapper should be configured'
        );

        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
            $bootstrappers,
            'Queue bootstrapper should be configured'
        );
    }

    public function test_database_config_has_central_connection(): void
    {
        $connection = config('database.connections.central');

        $this->assertNotNull($connection, 'Central database connection should exist');
        $this->assertIsArray($connection);
        $this->assertArrayHasKey('driver', $connection);
    }

    public function test_database_config_has_tenant_template_connection(): void
    {
        $connection = config('database.connections.tenant_template');

        $this->assertNotNull($connection, 'Tenant template connection should exist');
        $this->assertIsArray($connection);
        $this->assertArrayHasKey('driver', $connection);
    }

    public function test_tenancy_database_config_references_correct_connections(): void
    {
        $this->assertEquals(
            'central',
            config('tenancy.database.central_connection'),
            'Tenancy should reference central connection'
        );

        $this->assertEquals(
            'tenant_template',
            config('tenancy.database.template_tenant_connection'),
            'Tenancy should reference tenant_template connection'
        );
    }

    public function test_uses_tenant_connection_trait_exists(): void
    {
        $this->assertTrue(
            trait_exists(UsesTenantConnection::class),
            'UsesTenantConnection trait should exist'
        );
    }

    public function test_uses_tenant_connection_trait_returns_null_in_testing(): void
    {
        // In testing environment, UsesTenantConnection returns null (default connection)
        // to avoid database isolation issues with in-memory SQLite and MySQL lock timeouts
        $model = new class () extends \Illuminate\Database\Eloquent\Model {
            use UsesTenantConnection;

            protected $table = 'test';
        };

        // In testing mode, the trait returns null to use the default connection
        $this->assertNull($model->getConnectionName());
    }

    public function test_tenancy_central_domains_configured(): void
    {
        $domains = config('tenancy.central_domains');

        $this->assertIsArray($domains);
        $this->assertNotEmpty($domains);
    }

    public function test_tenancy_id_generator_configured(): void
    {
        $generator = config('tenancy.id_generator');

        $this->assertNotNull($generator);
        $this->assertTrue(class_exists($generator));
    }

    // ========================================
    // Security & Isolation Configuration Tests
    // ========================================

    public function test_tenancy_service_provider_is_registered(): void
    {
        $providers = require base_path('bootstrap/providers.php');

        $this->assertContains(
            \App\Providers\TenancyServiceProvider::class,
            $providers,
            'TenancyServiceProvider must be registered in bootstrap/providers.php'
        );
    }

    public function test_filesystem_bootstrapper_configured(): void
    {
        $bootstrappers = config('tenancy.bootstrappers');

        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
            $bootstrappers,
            'Filesystem bootstrapper should be configured for file isolation'
        );
    }

    public function test_tenant_model_is_not_using_tenant_connection(): void
    {
        // The Tenant model itself should use the central connection, not tenant connection
        $tenant = new Tenant();

        $this->assertNotEquals(
            'tenant',
            $tenant->getConnectionName(),
            'Tenant model should NOT use tenant connection (it is a central model)'
        );
    }

    public function test_user_model_does_not_use_tenant_connection(): void
    {
        // User is a central model and should not use tenant connection
        $user = new \App\Models\User();

        $this->assertNotEquals(
            'tenant',
            $user->getConnectionName(),
            'User model should NOT use tenant connection (it is a central model)'
        );
    }

    public function test_team_model_does_not_use_tenant_connection(): void
    {
        // Team is a central model and should not use tenant connection
        $team = new \App\Models\Team();

        $this->assertNotEquals(
            'tenant',
            $team->getConnectionName(),
            'Team model should NOT use tenant connection (it is a central model)'
        );
    }

    public function test_tenancy_service_provider_has_delete_database_job(): void
    {
        // Verify TenancyServiceProvider configures database deletion job
        // This is done via the TenantDeleted event listener in TenancyServiceProvider::events()
        $provider = new \App\Providers\TenancyServiceProvider(app());
        $events = $provider->events();

        $this->assertArrayHasKey(
            \Stancl\Tenancy\Events\TenantDeleted::class,
            $events,
            'TenantDeleted event should be configured'
        );

        // The event handler pipeline should contain DeleteDatabase job
        $tenantDeletedHandlers = $events[\Stancl\Tenancy\Events\TenantDeleted::class];
        $this->assertNotEmpty(
            $tenantDeletedHandlers,
            'TenantDeleted should have handlers for database cleanup'
        );
    }

    public function test_tenancy_prefix_is_configured(): void
    {
        // Verify database naming uses consistent prefix
        $prefix = config('tenancy.database.prefix');

        $this->assertNotEmpty(
            $prefix,
            'Tenant database prefix should be configured'
        );
    }

    public function test_tenancy_suffix_is_configured(): void
    {
        // Verify database naming uses suffix for identification
        $suffix = config('tenancy.database.suffix');

        $this->assertIsString(
            $suffix,
            'Tenant database suffix should be configured (even if empty)'
        );
    }

    public function test_tenant_connection_is_separate_from_default(): void
    {
        // Verify tenant_template connection is not the default connection
        $default = config('database.default');
        $tenantTemplate = 'tenant_template';

        $this->assertNotEquals(
            $default,
            $tenantTemplate,
            'Tenant template connection should be separate from default'
        );
    }

    public function test_central_connection_matches_default(): void
    {
        // For POC, central connection should match default
        // In production, this might differ
        $central = config('database.connections.central');
        $default = config('database.connections.' . config('database.default'));

        $this->assertEquals(
            $central['driver'],
            $default['driver'],
            'Central connection should use same driver as default for POC'
        );
    }
}
