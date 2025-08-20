<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Exchange\Services;

use App\Domain\Exchange\Events\FeeTierUpdated;
use App\Domain\Exchange\Events\UserFeeTierAssigned;
use App\Domain\Exchange\Services\FeeTierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FeeTierServiceTest extends TestCase
{
    use RefreshDatabase;

    private FeeTierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FeeTierService();
        Event::fake();
        Cache::flush();
    }

    public function test_calculates_user_fee_tier_based_on_volume(): void
    {
        // Arrange
        $userId = 'user-123';
        $this->createOrdersWithVolume($userId, 75000); // $75k volume

        // Act
        $tier = $this->service->getUserFeeTier($userId);

        // Assert
        $this->assertEquals('Silver', $tier['tier']['name']);
        $this->assertEquals(20, $tier['tier']['maker_fee']);
        $this->assertEquals(30, $tier['tier']['taker_fee']);
        $this->assertEquals(75000, $tier['monthly_volume']);
        $this->assertNotNull($tier['next_tier']);
        $this->assertEquals(175000, $tier['volume_to_next']); // $250k - $75k
    }

    public function test_assigns_user_to_specific_tier(): void
    {
        // Arrange
        $userId = 'user-456';

        // Act
        $this->service->assignUserFeeTier($userId, 'vip', 'Special promotion');

        // Assert
        Event::assertDispatched(UserFeeTierAssigned::class, function ($event) use ($userId) {
            return $event->userId === $userId
                && $event->tier === 'vip'
                && $event->reason === 'Special promotion';
        });

        $tier = $this->service->getUserFeeTier($userId);
        $this->assertEquals('VIP', $tier['tier']['name']);
    }

    public function test_calculates_order_fees_with_discounts(): void
    {
        // Arrange
        $userId = 'user-789';
        $poolId = 'pool-123';
        $this->createOrdersWithVolume($userId, 250000); // Gold tier (min $250k)
        $this->createPool($poolId, 'BTC', 'USDT');

        // Act
        $fees = $this->service->calculateOrderFees(
            $userId,
            $poolId,
            'limit', // Maker order
            1.0,
            50000
        );

        // Assert
        $this->assertEquals('Gold', $fees['fee_tier']);
        $this->assertEquals(15, $fees['base_fee_bps']); // Gold maker fee
        $this->assertEquals(15, $fees['effective_fee_bps']); // No discount
        $this->assertEquals(75, $fees['fee_amount']); // 0.15% of $50k
        $this->assertEquals(50000, $fees['order_value']);
    }

    public function test_identifies_stable_pair_fee_tier(): void
    {
        // Arrange
        $poolId = 'stable-pool';
        // Create pool without custom fee tier so default logic applies
        DB::table('liquidity_pools')->insert([
            'pool_id'        => $poolId,
            'base_currency'  => 'USDT',
            'quote_currency' => 'USDC',
            'base_reserve'   => '100',
            'quote_reserve'  => '500000',
            'is_active'      => true,
            'metadata'       => json_encode([]), // No custom fee_tier
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Act
        $feeTier = $this->service->getPoolFeeTier($poolId);

        // Assert
        $this->assertEquals(5, $feeTier); // 0.05% for stable pairs
    }

    public function test_identifies_exotic_pair_fee_tier(): void
    {
        // Arrange
        $poolId = 'exotic-pool';
        // Create pool without custom fee tier so default logic applies
        DB::table('liquidity_pools')->insert([
            'pool_id'        => $poolId,
            'base_currency'  => 'DOGE',
            'quote_currency' => 'SHIB',
            'base_reserve'   => '100',
            'quote_reserve'  => '500000',
            'is_active'      => true,
            'metadata'       => json_encode([]), // No custom fee_tier
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        // Act
        $feeTier = $this->service->getPoolFeeTier($poolId);

        // Assert
        $this->assertEquals(100, $feeTier); // 1.00% for exotic pairs
    }

    public function test_updates_pool_fee_tier(): void
    {
        // Arrange
        $poolId = 'pool-update';
        $this->createPool($poolId, 'ETH', 'USDT');
        $oldFee = $this->service->getPoolFeeTier($poolId);

        // Act
        $this->service->updatePoolFeeTier($poolId, 25);

        // Assert - verify the fee was updated
        $newFee = $this->service->getPoolFeeTier($poolId);
        $this->assertEquals(25, $newFee);

        // Note: FeeTierUpdated extends ShouldBeStored, so it goes through
        // the event sourcing system rather than Laravel's event dispatcher.
        // The event is still created and can be stored, but won't be caught
        // by Event::fake(). This is expected behavior for event-sourced events.
    }

    public function test_calculates_fee_statistics(): void
    {
        // Arrange
        $this->createExecutedOrders();

        // Act
        $stats = $this->service->getFeeStatistics('monthly');

        // Assert
        $this->assertArrayHasKey('total_orders', $stats);
        $this->assertArrayHasKey('total_fees', $stats);
        $this->assertArrayHasKey('average_fee', $stats);
        $this->assertArrayHasKey('total_volume', $stats);
        $this->assertArrayHasKey('tier_distribution', $stats);
        $this->assertArrayHasKey('effective_fee_rate', $stats);
    }

    public function test_retail_tier_for_new_users(): void
    {
        // Arrange
        $userId = 'new-user';

        // Act
        $tier = $this->service->getUserFeeTier($userId);

        // Assert
        $this->assertEquals('Retail', $tier['tier']['name']);
        $this->assertEquals(30, $tier['tier']['maker_fee']);
        $this->assertEquals(40, $tier['tier']['taker_fee']);
        $this->assertEquals(0, $tier['monthly_volume']);
        $this->assertEquals(10000, $tier['volume_to_next']);
    }

    public function test_vip_tier_has_no_next_tier(): void
    {
        // Arrange
        $userId = 'whale';
        $this->createOrdersWithVolume($userId, 10000000); // $10M volume

        // Act
        $tier = $this->service->getUserFeeTier($userId);

        // Assert
        $this->assertEquals('VIP', $tier['tier']['name']);
        $this->assertNull($tier['next_tier']);
        $this->assertNull($tier['volume_to_next']);
    }

    private function createOrdersWithVolume(string $userId, float $totalVolume): void
    {
        $ordersCount = 10;
        $volumePerOrder = $totalVolume / $ordersCount;

        for ($i = 0; $i < $ordersCount; $i++) {
            DB::table('orders')->insert([
                'order_id'       => "order-{$i}",
                'account_id'     => $userId, // Required field
                'user_id'        => $userId,
                'type'           => 'buy', // Required enum
                'order_type'     => 'limit', // Required enum
                'base_currency'  => 'BTC', // Required field
                'quote_currency' => 'USDT', // Required field
                'status'         => 'executed',
                'amount'         => $volumePerOrder / 50000, // Assuming BTC price of $50k
                'price'          => 50000,
                'fee_amount'     => $volumePerOrder * 0.003,
                'executed_at'    => now()->subDays($i),
                'created_at'     => now()->subDays($i),
                'updated_at'     => now()->subDays($i),
            ]);
        }
    }

    private function createPool(string $poolId, string $baseCurrency, string $quoteCurrency): void
    {
        DB::table('liquidity_pools')->insert([
            'pool_id'        => $poolId,
            'base_currency'  => $baseCurrency,
            'quote_currency' => $quoteCurrency,
            'base_reserve'   => '100',
            'quote_reserve'  => '500000',
            'is_active'      => true, // Use is_active instead of status
            'metadata'       => json_encode(['fee_tier' => 30]),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function createExecutedOrders(): void
    {
        for ($i = 0; $i < 5; $i++) {
            DB::table('orders')->insert([
                'order_id'       => "stat-order-{$i}",
                'account_id'     => "user-{$i}", // Required field
                'user_id'        => "user-{$i}",
                'type'           => 'buy', // Required enum
                'order_type'     => 'limit', // Required enum
                'base_currency'  => 'BTC', // Required field
                'quote_currency' => 'USDT', // Required field
                'status'         => 'executed',
                'amount'         => 1,
                'price'          => 50000,
                'fee_amount'     => 150,
                'executed_at'    => now()->subHours($i),
                'created_at'     => now()->subHours($i),
                'updated_at'     => now()->subHours($i),
            ]);
        }
    }
}
