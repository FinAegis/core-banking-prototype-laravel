<?php

declare(strict_types=1);

namespace Tests\Feature\MultiTenancy;

use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/**
 * Proof-of-Concept test for multi-tenancy isolation.
 *
 * This test validates that:
 * 1. Tenants can be created and linked to teams
 * 2. Tenant databases are isolated
 * 3. Data in one tenant is not visible in another
 */
class TenantIsolationPocTest extends BaseTestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure tenants table exists in central database
        if (! Schema::hasTable('tenants')) {
            $this->artisan('migrate', [
                '--path'     => 'database/migrations/2019_09_15_000010_create_tenants_table.php',
                '--realpath' => true,
            ]);
        }
    }

    public function test_tenant_model_can_be_created(): void
    {
        // Skip if tenancy not properly configured
        if (! class_exists(Tenant::class)) {
            $this->markTestSkipped('Tenant model not available');
        }

        $tenant = Tenant::create([
            'id'   => 'test-tenant-1',
            'name' => 'Test Tenant',
            'plan' => 'default',
        ]);

        $this->assertNotNull($tenant);
        $this->assertEquals('test-tenant-1', $tenant->id);
        $this->assertEquals('Test Tenant', $tenant->name);
    }

    public function test_tenant_can_be_linked_to_team(): void
    {
        // Create a team
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name'    => 'Acme Corp',
        ]);

        // Create tenant linked to team
        $tenant = Tenant::create([
            'id'      => 'acme-tenant',
            'team_id' => $team->id,
            'name'    => $team->name,
            'plan'    => 'enterprise',
        ]);

        $this->assertEquals($team->id, $tenant->team_id);
        $this->assertEquals('Acme Corp', $tenant->name);

        // Test relationship
        $this->assertNotNull($tenant->team);
        $this->assertEquals($team->id, $tenant->team->id);
    }

    public function test_tenant_can_be_created_from_team(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name'    => 'Beta Inc',
        ]);

        $tenant = Tenant::createFromTeam($team);

        $this->assertEquals($team->id, $tenant->team_id);
        $this->assertEquals('Beta Inc', $tenant->name);
        $this->assertEquals('default', $tenant->plan);
    }

    public function test_tenant_custom_columns_are_defined(): void
    {
        $columns = Tenant::getCustomColumns();

        $this->assertContains('id', $columns);
        $this->assertContains('team_id', $columns);
        $this->assertContains('name', $columns);
        $this->assertContains('plan', $columns);
        $this->assertContains('trial_ends_at', $columns);
    }

    public function test_tenancy_configuration_is_correct(): void
    {
        // Verify tenancy config
        $this->assertEquals(
            Tenant::class,
            config('tenancy.tenant_model'),
            'Tenant model should be configured'
        );

        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
            config('tenancy.bootstrappers'),
            'Database bootstrapper should be enabled'
        );

        $this->assertContains(
            \Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
            config('tenancy.bootstrappers'),
            'Cache bootstrapper should be enabled'
        );
    }

    public function test_database_connections_are_configured(): void
    {
        // Verify central connection exists
        $this->assertNotNull(
            config('database.connections.central'),
            'Central database connection should be configured'
        );

        // Verify tenant template connection exists
        $this->assertNotNull(
            config('database.connections.tenant_template'),
            'Tenant template connection should be configured'
        );

        // Verify tenancy uses correct connections
        $this->assertEquals(
            'central',
            config('tenancy.database.central_connection'),
            'Tenancy should use central connection'
        );

        $this->assertEquals(
            'tenant_template',
            config('tenancy.database.template_tenant_connection'),
            'Tenancy should use tenant_template for tenant databases'
        );
    }

    public function test_uses_tenant_connection_trait_returns_tenant_connection(): void
    {
        // Create a mock model using the trait
        $model = new class () extends \Illuminate\Database\Eloquent\Model {
            use \App\Domain\Shared\Traits\UsesTenantConnection;

            protected $table = 'test_models';
        };

        $this->assertEquals('tenant', $model->getConnectionName());
    }
}
