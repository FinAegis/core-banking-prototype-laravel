<?php

namespace Tests\Unit\Domain\Stablecoin\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Stablecoin\Events\CollateralPositionLiquidated;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Stablecoin\Services\DemoStablecoinService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DemoStablecoinServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DemoStablecoinService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure demo settings
        $this->app['env'] = 'demo';
        Config::set('demo.features.auto_approve', true);
        Config::set('demo.domains.stablecoin.collateral_ratio', 1.5);
        Config::set('demo.domains.stablecoin.liquidation_threshold', 1.2);
        Config::set('demo.domains.stablecoin.stability_fee', 2.5);

        // Create required test data
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create Account records that will be referenced
        Account::create([
            'uuid'      => 'acc_123',
            'user_uuid' => $user1->uuid,
            'name'      => 'Test Account 123',
            'balance'   => 10000,
        ]);

        Account::create([
            'uuid'      => 'acc_456',
            'user_uuid' => $user2->uuid,
            'name'      => 'Test Account 456',
            'balance'   => 10000,
        ]);

        Account::create([
            'uuid'      => 'acc_1',
            'user_uuid' => $user1->uuid,
            'name'      => 'Test Account 1',
            'balance'   => 10000,
        ]);

        Account::create([
            'uuid'      => 'acc_2',
            'user_uuid' => $user2->uuid,
            'name'      => 'Test Account 2',
            'balance'   => 10000,
        ]);

        // Create required Stablecoin records
        Stablecoin::create([
            'code'                 => 'GUSD',
            'name'                 => 'GCU USD Stablecoin',
            'symbol'               => 'GUSD',
            'peg_asset_code'       => 'USD',
            'peg_ratio'            => 1.0,
            'target_price'         => 1.0,
            'stability_mechanism'  => 'collateralized',
            'collateral_ratio'     => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty'  => 0.1,
            'total_supply'         => 0,
            'max_supply'           => 1000000000,
            'mint_fee'             => 0.001,
            'burn_fee'             => 0.001,
            'precision'            => 6,
            'is_active'            => true,
            'minting_enabled'      => true,
            'burning_enabled'      => true,
        ]);

        Stablecoin::create([
            'code'                 => 'EUSD',
            'name'                 => 'EUR USD Stablecoin',
            'symbol'               => 'EUSD',
            'peg_asset_code'       => 'EUR',
            'peg_ratio'            => 1.0,
            'target_price'         => 1.0,
            'stability_mechanism'  => 'collateralized',
            'collateral_ratio'     => 1.5,
            'min_collateral_ratio' => 1.2,
            'liquidation_penalty'  => 0.1,
            'total_supply'         => 0,
            'max_supply'           => 1000000000,
            'mint_fee'             => 0.001,
            'burn_fee'             => 0.001,
            'precision'            => 6,
            'is_active'            => true,
            'minting_enabled'      => true,
            'burning_enabled'      => true,
        ]);

        $this->service = new DemoStablecoinService();
    }

    #[Test]
    public function it_can_mint_stablecoins_with_sufficient_collateral()
    {
        $transaction = $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1 // 1 ETH worth ~$2000
        );

        // Transaction is already known to be an array
        $this->assertStringStartsWith('demo_tx_', $transaction['id']);
        $this->assertEquals('mint', $transaction['type']);
        $this->assertEquals(1000, $transaction['amount']);
        $this->assertEquals(1, $transaction['collateral']);
        $this->assertEquals('completed', $transaction['status']);
        $this->assertTrue($transaction['metadata']['demo_mode']);

        // Verify position was created
        $position = StablecoinCollateralPosition::where('account_uuid', 'acc_123')
            ->where('stablecoin_code', 'GUSD')
            ->first();

        $this->assertNotNull($position);
        $this->assertEquals(1000000, $position->collateral_amount); // ETH in micro units
    }

    #[Test]
    public function it_throws_exception_for_insufficient_collateral()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient collateral');

        $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 0.1 // Only ~$200 worth, need $1500
        );
    }

    #[Test]
    public function it_can_burn_stablecoins_and_release_collateral()
    {
        // First mint some stablecoins
        $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        // Then burn some
        $transaction = $this->service->burn(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 500
        );

        // Transaction is already known to be an array
        $this->assertEquals('burn', $transaction['type']);
        $this->assertEquals(500, $transaction['amount']);

        // Verify position was updated
        $position = StablecoinCollateralPosition::where('account_uuid', 'acc_123')
            ->where('stablecoin_code', 'GUSD')
            ->first();

        $this->assertNotNull($position);
        $this->assertEquals(500, $position->debt_amount);
    }

    #[Test]
    public function it_closes_position_when_fully_burned()
    {
        // Use factory-created account to ensure uniqueness
        $account = Account::factory()->create();

        // First mint
        $this->service->mint(
            accountId: $account->uuid,
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        // Burn all using the original amount
        $this->service->burn(
            accountId: $account->uuid,
            stablecoinId: 'GUSD',
            amount: 1000
        );

        $position = StablecoinCollateralPosition::where('account_uuid', $account->uuid)
            ->where('stablecoin_code', 'GUSD')
            ->first();

        $this->assertEquals('closed', $position->status);
        $this->assertEquals(0, $position->debt_amount);
    }

    #[Test]
    public function it_can_adjust_collateral_position()
    {
        Event::fake();

        // First create a position
        $this->service->mint(
            accountId: 'acc_123',
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        $position = StablecoinCollateralPosition::where('account_uuid', 'acc_123')->first();

        // Add more collateral
        $result = $this->service->adjustPosition(
            positionId: $position->uuid,
            collateral: 0.5,
            debt: 0
        );

        $this->assertArrayHasKey('position_id', $result);
        $this->assertArrayHasKey('collateral', $result);
        $this->assertArrayHasKey('health', $result);
    }

    #[Test]
    public function it_can_get_position_details()
    {
        // Clear all positions to ensure clean state
        StablecoinCollateralPosition::query()->delete();

        // Create account first
        $account = Account::factory()->create();

        // Create a position
        $this->service->mint(
            accountId: $account->uuid,
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        $position = StablecoinCollateralPosition::where('account_uuid', $account->uuid)->first();

        $details = $this->service->getPosition($position->uuid);

        $this->assertEquals($position->uuid, $details['position_id']);
        $this->assertEquals($account->uuid, $details['account_id']);
        $this->assertEquals('GUSD', $details['stablecoin_id']);
        $this->assertEquals(1, $details['collateral']);
        $this->assertEquals(1000, $details['debt']); // Should be the original amount
        $this->assertEquals('healthy', $details['health']);
    }

    #[Test]
    public function it_can_check_and_liquidate_at_risk_positions()
    {
        Event::fake();

        // Create an at-risk position (collateral in micro units)
        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_risk_1',
            'account_uuid'          => 'acc_456',
            'stablecoin_code'       => 'GUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 500000, // 0.5 ETH in micro units
            'debt_amount'           => 1000,
            'collateral_ratio'      => 1.0, // Below threshold
            'status'                => 'active',
        ]);

        $result = $this->service->checkLiquidations();

        $this->assertEquals(1, $result['checked']);
        $this->assertEquals(1, $result['liquidated']);
        $this->assertCount(1, $result['liquidation_details']);

        Event::assertDispatched(CollateralPositionLiquidated::class);
    }

    #[Test]
    public function it_provides_system_statistics()
    {
        // Create some positions (collateral in micro units)
        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_1',
            'account_uuid'          => 'acc_1',
            'stablecoin_code'       => 'GUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 1000000, // 1 ETH in micro units
            'debt_amount'           => 1000,
            'collateral_ratio'      => 2.0,
            'status'                => 'active',
        ]);

        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_2',
            'account_uuid'          => 'acc_2',
            'stablecoin_code'       => 'GUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 500000, // 0.5 ETH in micro units
            'debt_amount'           => 500,
            'collateral_ratio'      => 2.0,
            'status'                => 'active',
        ]);

        $stats = $this->service->getSystemStats();

        $this->assertEquals(1500, $stats['total_minted']);
        $this->assertEquals(1500000, $stats['total_collateral']); // 1.5 ETH in micro units
        $this->assertEquals(1500, $stats['total_debt']);
        $this->assertEquals(2, $stats['active_positions']);
        $this->assertTrue($stats['demo']);
    }

    #[Test]
    public function it_can_get_account_positions()
    {
        // Create positions for an account (collateral in micro units)
        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_1',
            'account_uuid'          => 'acc_123',
            'stablecoin_code'       => 'GUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 1000000, // 1 ETH in micro units
            'debt_amount'           => 1000,
            'collateral_ratio'      => 2.0,
            'status'                => 'active',
        ]);

        StablecoinCollateralPosition::create([
            'uuid'                  => 'pos_2',
            'account_uuid'          => 'acc_123',
            'stablecoin_code'       => 'EUSD',
            'collateral_asset_code' => 'ETH',
            'collateral_amount'     => 500000, // 0.5 ETH in micro units
            'debt_amount'           => 500,
            'collateral_ratio'      => 2.0,
            'status'                => 'active',
        ]);

        $result = $this->service->getAccountPositions('acc_123');

        $this->assertEquals('acc_123', $result['account_id']);
        $this->assertCount(2, $result['positions']);
        $this->assertEquals(1500000, $result['total_collateral']); // 1.5 ETH in micro units
        $this->assertEquals(1500, $result['total_debt']);
    }

    #[Test]
    public function it_prevents_burning_more_than_debt()
    {
        // Use factory-created account to ensure uniqueness
        $account = Account::factory()->create();

        // Mint some stablecoins
        $this->service->mint(
            accountId: $account->uuid,
            stablecoinId: 'GUSD',
            amount: 1000,
            collateral: 1
        );

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot burn more than debt amount');

        // Try to burn more than what was minted
        $this->service->burn(
            accountId: $account->uuid,
            stablecoinId: 'GUSD',
            amount: 1001
        );
    }

    #[Test]
    public function it_throws_exception_when_no_position_exists_for_burn()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No active position found');

        $this->service->burn(
            accountId: 'acc_999',
            stablecoinId: 'GUSD',
            amount: 100
        );
    }
}
