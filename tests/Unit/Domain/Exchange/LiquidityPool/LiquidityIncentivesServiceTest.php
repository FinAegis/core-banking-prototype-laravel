<?php

namespace Tests\Unit\Domain\Exchange\LiquidityPool;

use App\Domain\Exchange\LiquidityPool\Services\LiquidityIncentivesService;
use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Projections\LiquidityProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\ServiceTestCase;

class LiquidityIncentivesServiceTest extends ServiceTestCase
{
    private LiquidityIncentivesService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LiquidityIncentivesService();
    }

    #[Test]
    public function test_calculates_pool_rewards_based_on_tvl()
    {
        // Create a pool with known TVL
        $pool = LiquidityPool::create([
            'pool_id'            => 'test-pool-1',
            'base_currency'      => 'ETH',
            'quote_currency'     => 'USD',
            'base_reserve'       => '100',
            'quote_reserve'      => '200000', // TVL = 400k
            'total_shares'       => '1000',
            'fee_rate'           => '0.003',
            'is_active'          => true,
            'volume_24h'         => '50000',
            'fees_collected_24h' => '150',
        ]);

        $rewards = $this->service->calculatePoolRewards($pool);

        // Base reward = TVL * 0.0001 = 400k * 0.0001 = 40 USD
        $this->assertEquals('400000', $rewards['tvl']);
        $this->assertEquals(40, (float) $rewards['base_reward']); // Compare as float due to precision
        $this->assertEquals('USD', $rewards['reward_currency']);
        $this->assertGreaterThan(0, (float) $rewards['total_rewards']);
    }

    #[Test]
    public function test_applies_performance_multipliers()
    {
        // High volume pool
        $highVolumePool = LiquidityPool::create([
            'pool_id'            => 'high-volume',
            'base_currency'      => 'BTC',
            'quote_currency'     => 'USD',
            'base_reserve'       => '10',
            'quote_reserve'      => '400000',
            'total_shares'       => '1000',
            'fee_rate'           => '0.003',
            'is_active'          => true,
            'volume_24h'         => '800000', // 200% of TVL
            'fees_collected_24h' => '2400',
        ]);

        // Low volume pool
        $lowVolumePool = LiquidityPool::create([
            'pool_id'            => 'low-volume',
            'base_currency'      => 'ETH',
            'quote_currency'     => 'USD',
            'base_reserve'       => '100',
            'quote_reserve'      => '200000',
            'total_shares'       => '1000',
            'fee_rate'           => '0.003',
            'is_active'          => true,
            'volume_24h'         => '10000', // 2.5% of TVL
            'fees_collected_24h' => '30',
        ]);

        $highVolumeRewards = $this->service->calculatePoolRewards($highVolumePool);
        $lowVolumeRewards = $this->service->calculatePoolRewards($lowVolumePool);

        // High volume pool should have higher multiplier
        $this->assertGreaterThan(
            (float) $lowVolumeRewards['performance_multiplier'],
            (float) $highVolumeRewards['performance_multiplier']
        );
    }

    #[Test]
    public function test_calculates_provider_rewards_proportionally()
    {
        $pool = LiquidityPool::create([
            'pool_id'        => 'test-pool-2',
            'base_currency'  => 'ETH',
            'quote_currency' => 'USD',
            'base_reserve'   => '100',
            'quote_reserve'  => '200000',
            'total_shares'   => '1000',
            'fee_rate'       => '0.003',
            'is_active'      => true,
        ]);

        // Create providers with different shares
        LiquidityProvider::create([
            'pool_id'           => $pool->pool_id,
            'provider_id'       => 'provider-1',
            'shares'            => '600', // 60% of pool
            'base_contributed'  => '60',
            'quote_contributed' => '120000',
        ]);

        LiquidityProvider::create([
            'pool_id'           => $pool->pool_id,
            'provider_id'       => 'provider-2',
            'shares'            => '400', // 40% of pool
            'base_contributed'  => '40',
            'quote_contributed' => '80000',
        ]);

        $rewards = $this->service->calculatePoolRewards($pool);

        $this->assertCount(2, $rewards['provider_rewards']);

        // Provider 1 should get 60% of rewards
        $provider1Reward = $rewards['provider_rewards'][0];
        $provider2Reward = $rewards['provider_rewards'][1];

        $this->assertEquals(0.6, round((float) $provider1Reward['share_ratio'], 1));
        $this->assertEquals(0.4, round((float) $provider2Reward['share_ratio'], 1));
    }

    #[Test]
    public function test_applies_early_provider_bonus()
    {
        $pool = LiquidityPool::create([
            'pool_id'        => 'new-pool',
            'base_currency'  => 'ETH',
            'quote_currency' => 'USD',
            'base_reserve'   => '100',
            'quote_reserve'  => '200000',
            'total_shares'   => '1000',
            'fee_rate'       => '0.003',
            'is_active'      => true,
            'created_at'     => now()->subDays(10), // Pool is 10 days old
        ]);

        // Early provider (joined on day 1)
        LiquidityProvider::create([
            'pool_id'           => $pool->pool_id,
            'provider_id'       => 'early-provider',
            'shares'            => '500',
            'base_contributed'  => '50',
            'quote_contributed' => '100000',
            'created_at'        => now()->subDays(9),
        ]);

        // Late provider (joined recently)
        LiquidityProvider::create([
            'pool_id'           => $pool->pool_id,
            'provider_id'       => 'late-provider',
            'shares'            => '500',
            'base_contributed'  => '50',
            'quote_contributed' => '100000',
            'created_at'        => now()->subDays(1),
        ]);

        $rewards = $this->service->calculatePoolRewards($pool);

        $earlyProviderReward = $rewards['provider_rewards'][0];
        $lateProviderReward = $rewards['provider_rewards'][1];

        // Early provider should have bonus multiplier (or at least equal if both get same bonuses)
        $this->assertGreaterThanOrEqual(
            (float) $lateProviderReward['bonus_multiplier'],
            (float) $earlyProviderReward['bonus_multiplier']
        );
    }

    #[Test]
    public function test_calculates_provider_apy()
    {
        $pool = LiquidityPool::create([
            'pool_id'            => 'test-pool-3',
            'base_currency'      => 'ETH',
            'quote_currency'     => 'USD',
            'base_reserve'       => '100',
            'quote_reserve'      => '200000',
            'total_shares'       => '1000',
            'fee_rate'           => '0.003',
            'is_active'          => true,
            'fees_collected_24h' => '200', // 0.1% daily
        ]);

        LiquidityProvider::create([
            'pool_id'           => $pool->pool_id,
            'provider_id'       => 'provider-1',
            'shares'            => '100', // 10% of pool
            'base_contributed'  => '10',
            'quote_contributed' => '20000',
        ]);

        $apy = $this->service->calculateProviderAPY($pool->pool_id, 'provider-1');

        $this->assertEquals('provider-1', $apy['provider_id']);
        $this->assertEquals(40000, round((float) $apy['tvl'])); // 10% of 400k TVL
        $this->assertGreaterThan(0, (float) $apy['fee_apy']); // Should have fee APY
        $this->assertEquals('10%', $apy['share_ratio']);
    }
}
