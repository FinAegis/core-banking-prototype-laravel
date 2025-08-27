<?php

namespace Tests\Unit\Domain\Account\Actions;

use App\Domain\Account\Actions\UnfreezeAccount;
use App\Domain\Account\Events\AccountUnfrozen;
use App\Domain\Account\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\DomainTestCase;

class UnfreezeAccountTest extends DomainTestCase
{
    use RefreshDatabase;

    private UnfreezeAccount $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new UnfreezeAccount();
    }

    #[Test]
    public function test_unfreezes_frozen_account_successfully(): void
    {
        // Create frozen account
        $account = Account::factory()->create([
            'uuid'   => 'frozen-account-123',
            'name'   => 'Frozen Account',
            'frozen' => true,
        ]);

        // Create event
        $event = Mockery::mock(AccountUnfrozen::class);
        $event->shouldReceive('aggregateRootUuid')->andReturn('frozen-account-123');

        // Execute
        $this->action->__invoke($event);

        // Assert
        $account->refresh();
        $this->assertFalse($account->frozen);
    }

    #[Test]
    public function test_unfreezes_already_unfrozen_account(): void
    {
        // Create active account
        $account = Account::factory()->create([
            'uuid'   => 'active-account',
            'name'   => 'Already Active Account',
            'frozen' => false,
        ]);

        // Create event
        $event = Mockery::mock(AccountUnfrozen::class);
        $event->shouldReceive('aggregateRootUuid')->andReturn('active-account');

        // Execute
        $this->action->__invoke($event);

        // Assert
        $account->refresh();
        $this->assertFalse($account->frozen);
    }

    #[Test]
    public function test_throws_exception_if_account_not_found(): void
    {
        // Create event for non-existent account
        $event = Mockery::mock(AccountUnfrozen::class);
        $event->shouldReceive('aggregateRootUuid')->andReturn('non-existent-uuid');

        // Assert exception
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        // Execute
        $this->action->__invoke($event);
    }

    #[Test]
    public function test_unfreezes_account_preserving_balances(): void
    {
        // Create frozen account with balances
        $account = Account::factory()->create([
            'uuid'   => 'frozen-with-balance',
            'name'   => 'Frozen Account with Balance',
            'frozen' => true,
        ]);

        // Create account balances
        $account->balances()->create([
            'asset_code' => 'USD',
            'balance'    => 25000,
        ]);

        $account->balances()->create([
            'asset_code' => 'BTC',
            'balance'    => 50000000, // 0.5 BTC
        ]);

        // Create event
        $event = Mockery::mock(AccountUnfrozen::class);
        $event->shouldReceive('aggregateRootUuid')->andReturn('frozen-with-balance');

        // Execute
        $this->action->__invoke($event);

        // Assert
        $account->refresh();
        $this->assertFalse($account->frozen);

        // Verify balances are unchanged
        $this->assertEquals(25000, $account->balances()->where('asset_code', 'USD')->first()->balance);
        $this->assertEquals(50000000, $account->balances()->where('asset_code', 'BTC')->first()->balance);
    }

    #[Test]
    public function test_unfreezes_business_account(): void
    {
        // Create frozen business account
        $account = Account::factory()->create([
            'uuid'   => 'frozen-business',
            'name'   => 'Frozen Business Account',
            'frozen' => true,
        ]);

        // Create event
        $event = Mockery::mock(AccountUnfrozen::class);
        $event->shouldReceive('aggregateRootUuid')->andReturn('frozen-business');

        // Execute
        $this->action->__invoke($event);

        // Assert
        $account->refresh();
        $this->assertFalse($account->frozen);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
