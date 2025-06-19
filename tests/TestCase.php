<?php

namespace Tests;

use App\Domain\Account\Aggregates\LedgerAggregate;
use App\Domain\Account\Repositories\AccountRepository;
use App\Domain\Account\Services\AccountService;
use App\Models\Account;
use App\Models\Role;
use App\Models\User;
use App\Values\DefaultAccountNames;
use App\Values\UserRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Str;
use Tests\Domain\Account\Aggregates\LedgerAggregateTest;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected User $user;

    protected User $business_user;

    protected Account $account;
    
    protected function tearDown(): void
    {
        parent::tearDown();
        
        // Close any Mockery mocks
        \Mockery::close();
    }

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Set up parallel testing tokens for isolated Redis and cache prefixes
        $this->setUpParallelTesting();

        $this->createRoles();

        $this->user = User::factory()->create();
        $this->business_user = User::factory()->withBusinessRole()->create();
        $this->account = $this->createAccount($this->business_user);
    }

    /**
     * @throws \Throwable
     */
    protected function assertExceptionThrown( callable $callable, string $expectedExceptionClass): void
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

    /**
     * @param User $user
     *
     * @return Account
     */
    protected function createAccount(User $user): Account
    {
        $uuid = Str::uuid();

        app( LedgerAggregate::class )->retrieve( $uuid )
                                     ->createAccount(
                                         hydrate(
                                             class: \App\Domain\Account\DataObjects\Account::class,
                                             properties: [
                                                 'name'      => DefaultAccountNames::default(
                                                 ),
                                                 'user_uuid' => $user->uuid,
                                             ]
                                         )
                                     )
                                     ->persist();

        return app( AccountRepository::class )->findByUuid( $uuid );
    }

    /**
     * @return void
     */
    protected function createRoles(): void
    {
        // Check if roles already exist in the database
        $existingRoles = Role::whereIn('name', array_column(UserRoles::cases(), 'value'))->count();
        
        if ($existingRoles >= count(UserRoles::cases())) {
            return;
        }
        
        // Use a database transaction to ensure atomic operation
        \DB::transaction(function () {
            collect(UserRoles::cases())->each(function ($role) {
                Role::firstOrCreate(
                    ['name' => $role->value],
                    ['guard_name' => 'web']
                );
            });
        });
    }

    /**
     * Set up parallel testing isolation for Redis and cache.
     *
     * @return void
     */
    protected function setUpParallelTesting(): void
    {
        $token = ParallelTesting::token();
        
        if ($token) {
            // Prefix Redis connections for isolation
            config([
                'database.redis.options.prefix' => 'test_' . $token . ':',
                'cache.prefix' => 'test_' . $token,
                'horizon.prefix' => 'test_' . $token . '_horizon:',
            ]);

            // Ensure event sourcing uses isolated storage
            config([
                'event-sourcing.storage_prefix' => 'test_' . $token,
            ]);
        }
    }
}
