<?php

namespace Tests;

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Repositories\AccountRepository;
use App\Domain\Account\Values\DefaultAccountNames;
use App\Domain\User\Values\UserRoles;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Str;
use Mockery;
use ReflectionClass;
use Tests\Traits\TracksTestPerformance;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;
    use TracksTestPerformance;

    protected User $user;

    protected User $business_user;

    protected Account $account;

    protected function tearDown(): void
    {
        // Track test performance
        $this->trackTestPerformance();

        parent::tearDown();

        // Close any Mockery mocks
        Mockery::close();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Set up performance tracking
        $this->setUpPerformanceTracking();

        // Set up parallel testing tokens for isolated Redis and cache prefixes
        $this->setUpParallelTesting();

        $this->createRoles();

        // Only create default users and accounts if the test needs them
        if ($this->shouldCreateDefaultAccountsInSetup()) {
            $this->user = User::factory()->create();
            $this->business_user = User::factory()->withBusinessRole()->create();
            $this->account = $this->createAccount($this->business_user);
        }

        // Set up Filament panel if we're in a Filament test directory
        $testFile = (new ReflectionClass($this))->getFileName();
        if (str_contains($testFile, '/Filament/') || str_contains($testFile, '\\Filament\\')) {
            $this->setUpFilament();
        }
    }

    /**
     * Determine if this test should create default accounts in setUp.
     * Override in child classes to disable.
     */
    protected function shouldCreateDefaultAccountsInSetup(): bool
    {
        // Don't create accounts for Security tests by default to avoid transaction conflicts
        $testFile = (new ReflectionClass($this))->getFileName();
        if (str_contains($testFile, '/Security/') || str_contains($testFile, '\\Security\\')) {
            return false;
        }

        return true;
    }

    /**
     * Create default users and accounts if they don't exist.
     */
    protected function createDefaultAccounts(): void
    {
        if (! isset($this->user)) {
            $this->user = User::factory()->create();
        }

        if (! isset($this->business_user)) {
            $this->business_user = User::factory()->withBusinessRole()->create();
        }

        if (! isset($this->account)) {
            $this->account = $this->createAccount($this->business_user);
        }
    }

    /**
     * @throws Throwable
     */
    protected function assertExceptionThrown(callable $callable, string $expectedExceptionClass): void
    {
        try {
            $callable();

            $this->fail(
                "Expected exception `{$expectedExceptionClass}` was not thrown."
            );
        } catch (Throwable $exception) {
            if (! $exception instanceof $expectedExceptionClass) {
                throw $exception;
            }
            $this->assertInstanceOf($expectedExceptionClass, $exception);
        }
    }

    protected function createAccount(User $user): Account
    {
        $uuid = Str::uuid();

        app(LedgerAggregate::class)->retrieve($uuid)
            ->createAccount(
                hydrate(
                    class: \App\Domain\Account\DataObjects\Account::class,
                    properties: [
                        'name' => DefaultAccountNames::default(
                        ),
                        'user_uuid' => $user->uuid,
                    ]
                )
            )
            ->persist();

        return app(AccountRepository::class)->findByUuid($uuid);
    }

    protected function createRoles(): void
    {
        // Check if roles already exist in the database
        $existingRoles = Role::whereIn('name', array_column(UserRoles::cases(), 'value'))->count();

        if ($existingRoles >= count(UserRoles::cases())) {
            return;
        }

        // Create roles without transaction to avoid nesting issues
        collect(UserRoles::cases())->each(function ($role) {
            Role::firstOrCreate(
                ['name' => $role->value],
                ['guard_name' => 'web']
            );
        });
    }

    /**
     * Set up parallel testing isolation for Redis and cache.
     */
    protected function setUpParallelTesting(): void
    {
        $token = ParallelTesting::token();

        if ($token) {
            // Prefix Redis connections for isolation
            config([
                'database.redis.options.prefix' => 'test_' . $token . ':',
                'cache.prefix'                  => 'test_' . $token,
                'horizon.prefix'                => 'test_' . $token . '_horizon:',
            ]);

            // Ensure event sourcing uses isolated storage
            config([
                'event-sourcing.storage_prefix' => 'test_' . $token,
            ]);

            // Use separate database for each parallel process when using MySQL
            if (config('database.default') === 'mysql') {
                $database = config('database.connections.mysql.database');
                config([
                    'database.connections.mysql.database' => $database . '_test_' . $token,
                ]);
            }

            // Ensure unique constraint violations don't affect parallel tests
            config([
                'database.connections.sqlite.foreign_key_constraints' => false,
            ]);
        }
    }

    /**
     * Set up Filament for testing.
     */
    protected function setUpFilament(): void
    {
        // Register and set the admin panel as current
        $panel = Filament::getPanel('admin');

        if ($panel) {
            Filament::setCurrentPanel($panel);
            Filament::setServingStatus(true);
        }
    }
}
