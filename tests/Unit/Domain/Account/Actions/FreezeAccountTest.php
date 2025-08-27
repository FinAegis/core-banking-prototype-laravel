<?php

namespace Tests\Unit\Domain\Account\Actions;

use App\Domain\Account\Actions\FreezeAccount;
use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class FreezeAccountTest extends DomainTestCase
{
    use RefreshDatabase;

    private FreezeAccount $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new FreezeAccount();
    }

    #[Test]
    public function test_freezes_account_successfully(): void
    {
        // Create account
        $account = Account::factory()->create([
            'uuid'   => 'account-123',
            'name'   => 'Active Account',
            'frozen' => false,
        ]);

        // Create event
        $event = Mockery::mock(AccountFrozen::class);
        $event->shouldReceive('aggregateRootUuid')->andReturn('account-123');

        // Execute
        $this->action->__invoke($event);

        // Assert
        $account->refresh();
        $this->assertTrue($account->frozen);
    }

    #[Test]
    public function test_freezes_already_frozen_account(): void
    {
        // Create already frozen account
        $account = Account::factory()->create([
            'uuid'   => 'frozen-account',
            'name'   => 'Already Frozen Account',
            'frozen' => true,
        ]);

        // Create event
        $event = Mockery::mock(AccountFrozen::class);
        $event->shouldReceive('aggregateRootUuid')->andReturn('frozen-account');

        // Execute
        $this->action->__invoke($event);

        // Assert
        $account->refresh();
        $this->assertTrue($account->frozen);
    }

    #[Test]
    public function test_throws_exception_if_account_not_found(): void
    {
        // Create event for non-existent account
        $event = Mockery::mock(AccountFrozen::class);
        $event->shouldReceive('aggregateRootUuid')->andReturn('non-existent-uuid');

        // Assert exception
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // Execute
        $this->action->__invoke($event);
    }

    #[Test]
    public function test_freezes_account_with_balances(): void
    {
        // Create account with balances
        $account = Account::factory()->create([
            'uuid'   => 'account-with-balance',
            'name'   => 'Account with Balance',
            'frozen' => false,
        ]);

        // Create account balances
        $account->balances()->create([
            'asset_code' => 'USD',
            'balance'    => 10000,
        ]);

        $account->balances()->create([
            'asset_code' => 'EUR',
            'balance'    => 5000,
        ]);

        // Create event
        $event = Mockery::mock(AccountFrozen::class);
        $event->shouldReceive('aggregateRootUuid')->andReturn('account-with-balance');

        // Execute
        $this->action->__invoke($event);

        // Assert
        $account->refresh();
        $this->assertTrue($account->frozen);

        // Verify balances are unchanged
        $this->assertEquals(10000, $account->balances()->where('asset_code', 'USD')->first()->balance);
        $this->assertEquals(5000, $account->balances()->where('asset_code', 'EUR')->first()->balance);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
