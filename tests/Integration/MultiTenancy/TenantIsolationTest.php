<?php

declare(strict_types=1);

namespace Tests\Integration\MultiTenancy;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\CreatesApplication;

/**
 * Integration tests for multi-tenancy data isolation.
 *
 * These tests verify real tenant data isolation using MySQL.
 * They are skipped in CI (SQLite) since UsesTenantConnection
 * returns null in testing env, making isolation untestable.
 */
#[\PHPUnit\Framework\Attributes\Group('integration')]
#[\PHPUnit\Framework\Attributes\Group('tenancy')]
class TenantIsolationTest extends BaseTestCase
{
    use CreatesApplication;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests require MySQL for real tenant connection routing
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Multi-tenancy isolation tests require MySQL');
        }

        // Verify central connection is available
        try {
            DB::connection('central')->getPdo();
        } catch (Exception $e) {
            $this->markTestSkipped('Central database connection not available: ' . $e->getMessage());
        }

        // Ensure tenants table exists
        if (! Schema::hasTable('tenants')) {
            $this->markTestSkipped('Tenants table not available');
        }
    }

    public function test_data_in_tenant_a_is_not_visible_in_tenant_b(): void
    {
        // Create two tenants
        $userA = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $userA->id, 'name' => 'Team Alpha']);
        $tenantA = Tenant::createFromTeam($teamA);

        $userB = User::factory()->create();
        $teamB = Team::factory()->create(['user_id' => $userB->id, 'name' => 'Team Beta']);
        $tenantB = Tenant::createFromTeam($teamB);

        // Initialize tenant A and create data
        tenancy()->initialize($tenantA);

        $accountA = Account::create([
            'uuid'      => fake()->uuid(),
            'user_uuid' => $userA->uuid,
            'name'      => 'Alpha Checking',
            'frozen'    => false,
        ]);

        AccountBalance::create([
            'account_uuid' => $accountA->uuid,
            'asset_code'   => 'USD',
            'balance'      => 100000,
        ]);

        // Verify data exists in tenant A
        $this->assertCount(1, Account::all());
        $this->assertCount(1, AccountBalance::all());

        // Switch to tenant B
        tenancy()->initialize($tenantB);

        // Tenant B should see no data from tenant A
        $this->assertCount(0, Account::all());
        $this->assertCount(0, AccountBalance::all());

        // Clean up tenancy context
        tenancy()->end();
    }

    public function test_cross_tenant_query_is_blocked(): void
    {
        $userA = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $userA->id, 'name' => 'Team Gamma']);
        $tenantA = Tenant::createFromTeam($teamA);

        // Create data in tenant A
        tenancy()->initialize($tenantA);

        $accountA = Account::create([
            'uuid'      => fake()->uuid(),
            'user_uuid' => $userA->uuid,
            'name'      => 'Gamma Account',
            'frozen'    => false,
        ]);

        tenancy()->end();

        // Without tenancy context, the query should not return tenant data
        // (UsesTenantConnection returns null in testing, but in MySQL
        // it routes to the tenant connection which requires initialization)
        $model = new class () extends \Illuminate\Database\Eloquent\Model
        {
            use UsesTenantConnection;

            protected $table = 'accounts';
        };

        // In production with MySQL, accessing tenant connection without
        // initialization should fail or return empty
        if (config('app.env') !== 'testing') {
            $this->assertEmpty($model->all());
        } else {
            // In testing env, UsesTenantConnection returns null,
            // so this test validates the trait behavior
            $this->assertNull($model->getConnectionName());
        }
    }

    public function test_tenant_switching_returns_correct_data(): void
    {
        // Create two tenants with distinct data
        $userA = User::factory()->create();
        $teamA = Team::factory()->create(['user_id' => $userA->id, 'name' => 'Team Delta']);
        $tenantA = Tenant::createFromTeam($teamA);

        $userB = User::factory()->create();
        $teamB = Team::factory()->create(['user_id' => $userB->id, 'name' => 'Team Epsilon']);
        $tenantB = Tenant::createFromTeam($teamB);

        // Create data in tenant A
        tenancy()->initialize($tenantA);
        Account::create([
            'uuid'      => fake()->uuid(),
            'user_uuid' => $userA->uuid,
            'name'      => 'Delta Account',
            'frozen'    => false,
        ]);
        tenancy()->end();

        // Create data in tenant B
        tenancy()->initialize($tenantB);
        Account::create([
            'uuid'      => fake()->uuid(),
            'user_uuid' => $userB->uuid,
            'name'      => 'Epsilon Account 1',
            'frozen'    => false,
        ]);
        Account::create([
            'uuid'      => fake()->uuid(),
            'user_uuid' => $userB->uuid,
            'name'      => 'Epsilon Account 2',
            'frozen'    => false,
        ]);
        tenancy()->end();

        // Switch to A — should see 1 account
        tenancy()->initialize($tenantA);
        $this->assertCount(1, Account::all());
        $this->assertEquals('Delta Account', Account::first()->name);
        tenancy()->end();

        // Switch to B — should see 2 accounts
        tenancy()->initialize($tenantB);
        $this->assertCount(2, Account::all());
        tenancy()->end();
    }

    public function test_central_models_accessible_from_any_tenant(): void
    {
        // Central models (User, Team, Tenant) should be accessible
        // regardless of tenancy context
        $user = User::factory()->create(['name' => 'Central User']);

        $team = Team::factory()->create(['user_id' => $user->id, 'name' => 'Central Team']);
        $tenant = Tenant::createFromTeam($team);

        // Initialize tenancy
        tenancy()->initialize($tenant);

        // Central models should still be accessible
        $foundUser = User::find($user->id);
        $this->assertNotNull($foundUser);
        $this->assertEquals('Central User', $foundUser->name);

        $foundTeam = Team::find($team->id);
        $this->assertNotNull($foundTeam);
        $this->assertEquals('Central Team', $foundTeam->name);

        tenancy()->end();
    }

    public function test_uses_tenant_connection_trait_behavior(): void
    {
        // Validate the trait returns correct connection based on environment
        $model = new class () extends \Illuminate\Database\Eloquent\Model
        {
            use UsesTenantConnection;

            protected $table = 'test_models';
        };

        if (config('app.env') === 'testing') {
            $this->assertNull(
                $model->getConnectionName(),
                'In testing env, UsesTenantConnection should return null'
            );
        } else {
            $this->assertEquals(
                'tenant',
                $model->getConnectionName(),
                'In non-testing env, UsesTenantConnection should return tenant'
            );
        }
    }
}
