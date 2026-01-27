<?php

declare(strict_types=1);

namespace Tests\Unit\MultiTenancy;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
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
        $this->assertTrue(
            is_subclass_of(Tenant::class, \Stancl\Tenancy\Database\Models\Tenant::class),
            'Tenant model should extend stancl/tenancy base Tenant'
        );
    }

    public function test_tenant_model_implements_tenant_with_database(): void
    {
        $reflection = new \ReflectionClass(Tenant::class);

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
        $this->assertTrue(
            method_exists(Tenant::class, 'team'),
            'Tenant model should have team() relationship method'
        );
    }

    public function test_tenant_model_has_create_from_team_method(): void
    {
        $this->assertTrue(
            method_exists(Tenant::class, 'createFromTeam'),
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

    public function test_uses_tenant_connection_trait_returns_tenant_connection(): void
    {
        $model = new class extends \Illuminate\Database\Eloquent\Model {
            use UsesTenantConnection;

            protected $table = 'test';
        };

        $this->assertEquals('tenant', $model->getConnectionName());
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
}
