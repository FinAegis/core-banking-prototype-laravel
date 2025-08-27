<?php

namespace App\Domain\Exchange\LiquidityPool\Services;

use App\Domain\Exchange\Aggregates\LiquidityPool;
use App\Domain\Exchange\Projections\LiquidityPool as PoolProjection;
use App\Domain\Exchange\Projections\LiquidityProvider;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Exception;

class LiquidityIncentivesService
{
    // Reward distribution parameters
    private const BASE_REWARD_RATE = '0.0001'; // 0.01% per day

    private const PERFORMANCE_MULTIPLIER_MAX = '2.0'; // Max 2x rewards

    private const EARLY_LP_BONUS = '1.5'; // 50% bonus for early LPs

    private const LARGE_LP_BONUS = '1.2'; // 20% bonus for large positions

    // Thresholds
    private const EARLY_LP_THRESHOLD_DAYS = 30;

    private const LARGE_LP_THRESHOLD_PERCENT = '0.05'; // 5% of pool

    /**
     * Calculate and distribute rewards for all active pools.
     */
    public function distributeRewards(): array
    {
        $results = [];
        $activePools = PoolProjection::active()->get();

        foreach ($activePools as $pool) {
            try {
                $rewards = $this->calculatePoolRewards($pool);
                if ($rewards['total_rewards']->isGreaterThan(0)) {
                    $this->distributePoolRewards($pool, $rewards);
                    $results[$pool->pool_id] = [
                        'status'  => 'success',
                        'rewards' => $rewards,
                    ];
                }
            } catch (Exception $e) {
                $results[$pool->pool_id] = [
                    'status' => 'error',
                    'error'  => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Calculate rewards for a specific pool.
     */
    public function calculatePoolRewards(PoolProjection $pool): array
    {
        // Base reward calculation
        $tvl = $this->calculateTVL($pool);
        $baseReward = $tvl->multipliedBy(self::BASE_REWARD_RATE);

        // Apply performance multipliers
        $performanceMultiplier = $this->calculatePerformanceMultiplier($pool);
        $totalRewards = $baseReward->multipliedBy($performanceMultiplier);

        // Calculate individual provider rewards
        $providerRewards = $this->calculateProviderRewards($pool, $totalRewards);

        return [
            'pool_id'                => $pool->pool_id,
            'tvl'                    => $tvl->__toString(),
            'base_reward'            => $baseReward->__toString(),
            'performance_multiplier' => $performanceMultiplier->__toString(),
            'total_rewards'          => $totalRewards->__toString(),
            'provider_rewards'       => $providerRewards,
            'reward_currency'        => $pool->quote_currency, // Rewards paid in quote currency
        ];
    }

    /**
     * Calculate TVL (Total Value Locked) in quote currency.
     */
    private function calculateTVL(PoolProjection $pool): BigDecimal
    {
        $baseReserve = BigDecimal::of($pool->base_reserve);
        $quoteReserve = BigDecimal::of($pool->quote_reserve);

        // Calculate spot price
        $spotPrice = $baseReserve->isZero()
            ? BigDecimal::zero()
            : $quoteReserve->dividedBy($baseReserve, 18, RoundingMode::HALF_UP);

        // TVL = 2 * quote_reserve (for balanced pools)
        return $quoteReserve->multipliedBy(2);
    }

    /**
     * Calculate performance multiplier based on pool metrics.
     */
    private function calculatePerformanceMultiplier(PoolProjection $pool): BigDecimal
    {
        $multiplier = BigDecimal::one();

        // Volume multiplier (higher volume = higher rewards)
        $volumeMultiplier = $this->calculateVolumeMultiplier($pool);
        $multiplier = $multiplier->multipliedBy($volumeMultiplier);

        // Fee generation multiplier
        $feeMultiplier = $this->calculateFeeMultiplier($pool);
        $multiplier = $multiplier->multipliedBy($feeMultiplier);

        // Utilization multiplier (how efficiently the pool is being used)
        $utilizationMultiplier = $this->calculateUtilizationMultiplier($pool);
        $multiplier = $multiplier->multipliedBy($utilizationMultiplier);

        // Cap at maximum multiplier
        if ($multiplier->isGreaterThan(self::PERFORMANCE_MULTIPLIER_MAX)) {
            $multiplier = BigDecimal::of(self::PERFORMANCE_MULTIPLIER_MAX);
        }

        return $multiplier;
    }

    /**
     * Calculate volume-based multiplier.
     */
    private function calculateVolumeMultiplier(PoolProjection $pool): BigDecimal
    {
        $volume24h = BigDecimal::of($pool->volume_24h ?? '0');
        $tvl = $this->calculateTVL($pool);

        if ($tvl->isZero()) {
            return BigDecimal::one();
        }

        // Volume to TVL ratio (normalized to daily)
        $volumeRatio = $volume24h->dividedBy($tvl, 18, RoundingMode::HALF_UP);

        // Multiplier: 1 + (volume_ratio * 0.5), capped at 1.5
        $multiplier = BigDecimal::one()->plus($volumeRatio->multipliedBy('0.5'));

        return $multiplier->isGreaterThan('1.5')
            ? BigDecimal::of('1.5')
            : $multiplier;
    }

    /**
     * Calculate fee-based multiplier.
     */
    private function calculateFeeMultiplier(PoolProjection $pool): BigDecimal
    {
        $fees24h = BigDecimal::of($pool->fees_collected_24h ?? '0');
        $tvl = $this->calculateTVL($pool);

        if ($tvl->isZero()) {
            return BigDecimal::one();
        }

        // Daily fee yield
        $feeYield = $fees24h->dividedBy($tvl, 18, RoundingMode::HALF_UP);

        // Multiplier: 1 + (fee_yield * 100), capped at 1.2
        $multiplier = BigDecimal::one()->plus($feeYield->multipliedBy('100'));

        return $multiplier->isGreaterThan('1.2')
            ? BigDecimal::of('1.2')
            : $multiplier;
    }

    /**
     * Calculate utilization multiplier.
     */
    private function calculateUtilizationMultiplier(PoolProjection $pool): BigDecimal
    {
        // Check swap frequency
        $swapRelation = $pool->swaps();
        $swapCount24h = $swapRelation ? $swapRelation->where('created_at', '>=', now()->subDay())->count() : 0;

        // More swaps = better utilization
        $swapScore = min($swapCount24h / 100, 1.0); // Normalize to 0-1

        // Check provider diversity
        $providerCount = $pool->providers()->count();
        $diversityScore = min($providerCount / 10, 1.0); // Normalize to 0-1

        // Combined score
        $utilizationScore = ($swapScore + $diversityScore) / 2;

        // Multiplier: 1 + (utilization_score * 0.3)
        return BigDecimal::one()->plus(BigDecimal::of($utilizationScore)->multipliedBy('0.3'));
    }

    /**
     * Calculate individual provider rewards.
     */
    private function calculateProviderRewards(PoolProjection $pool, BigDecimal $totalRewards): array
    {
        $providers = $pool->providers;
        $providerRewards = [];

        $totalShares = BigDecimal::of($pool->total_shares);
        if ($totalShares->isZero()) {
            return [];
        }

        foreach ($providers as $provider) {
            $providerShares = BigDecimal::of($provider->shares);
            $shareRatio = $providerShares->dividedBy($totalShares, 18, RoundingMode::DOWN);

            // Base reward proportional to share
            $baseProviderReward = $totalRewards->multipliedBy($shareRatio);

            // Apply bonuses
            $bonusMultiplier = $this->calculateProviderBonuses($provider, $pool);
            $finalReward = $baseProviderReward->multipliedBy($bonusMultiplier);

            $providerRewards[] = [
                'provider_id'      => $provider->provider_id,
                'shares'           => $provider->shares,
                'share_ratio'      => $shareRatio->__toString(),
                'base_reward'      => $baseProviderReward->__toString(),
                'bonus_multiplier' => $bonusMultiplier->__toString(),
                'final_reward'     => $finalReward->__toString(),
            ];
        }

        return $providerRewards;
    }

    /**
     * Calculate provider-specific bonuses.
     */
    private function calculateProviderBonuses(LiquidityProvider $provider, PoolProjection $pool): BigDecimal
    {
        $multiplier = BigDecimal::one();

        // Early LP bonus
        if ($this->isEarlyProvider($provider, $pool)) {
            $multiplier = $multiplier->multipliedBy(self::EARLY_LP_BONUS);
        }

        // Large LP bonus
        if ($this->isLargeProvider($provider, $pool)) {
            $multiplier = $multiplier->multipliedBy(self::LARGE_LP_BONUS);
        }

        // Loyalty bonus (time in pool)
        $loyaltyMultiplier = $this->calculateLoyaltyBonus($provider);
        $multiplier = $multiplier->multipliedBy($loyaltyMultiplier);

        return $multiplier;
    }

    /**
     * Check if provider is an early LP.
     */
    private function isEarlyProvider(LiquidityProvider $provider, PoolProjection $pool): bool
    {
        $poolAge = $pool->created_at->diffInDays(now());
        $providerAge = $provider->created_at->diffInDays(now());

        return $poolAge <= self::EARLY_LP_THRESHOLD_DAYS &&
               $providerAge >= ($poolAge * 0.8); // Was there for 80% of pool lifetime
    }

    /**
     * Check if provider is a large LP.
     */
    private function isLargeProvider(LiquidityProvider $provider, PoolProjection $pool): bool
    {
        $providerShares = BigDecimal::of($provider->shares);
        $totalShares = BigDecimal::of($pool->total_shares);

        if ($totalShares->isZero()) {
            return false;
        }

        $shareRatio = $providerShares->dividedBy($totalShares, 18, RoundingMode::DOWN);

        return $shareRatio->isGreaterThanOrEqualTo(self::LARGE_LP_THRESHOLD_PERCENT);
    }

    /**
     * Calculate loyalty bonus based on time in pool.
     */
    private function calculateLoyaltyBonus(LiquidityProvider $provider): BigDecimal
    {
        $daysInPool = $provider->created_at->diffInDays(now());

        // 0.1% bonus per week, capped at 20%
        $weeklyBonus = BigDecimal::of('0.001');
        $weeks = floor($daysInPool / 7);

        $bonus = BigDecimal::one()->plus($weeklyBonus->multipliedBy($weeks));

        return $bonus->isGreaterThan('1.2')
            ? BigDecimal::of('1.2')
            : $bonus;
    }

    /**
     * Distribute rewards to providers.
     */
    private function distributePoolRewards(PoolProjection $pool, array $rewards): void
    {
        $poolAggregate = LiquidityPool::retrieve($pool->pool_id);

        // Distribute rewards
        $poolAggregate->distributeRewards(
            rewardAmount: $rewards['total_rewards'],
            rewardCurrency: $rewards['reward_currency'],
            metadata: [
                'distribution_type'      => 'automated',
                'performance_multiplier' => $rewards['performance_multiplier'],
                'provider_count'         => count($rewards['provider_rewards']),
            ]
        )->persist();
    }

    /**
     * Calculate APY for liquidity providers.
     */
    public function calculateProviderAPY(string $poolId, string $providerId): array
    {
        $pool = PoolProjection::where('pool_id', $poolId)->firstOrFail();
        $provider = LiquidityProvider::where('pool_id', $poolId)
            ->where('provider_id', $providerId)
            ->firstOrFail();

        // Get provider's share of pool
        $providerShares = BigDecimal::of($provider->shares);
        $totalShares = BigDecimal::of($pool->total_shares);
        $shareRatio = $totalShares->isZero()
            ? BigDecimal::zero()
            : $providerShares->dividedBy($totalShares, 18, RoundingMode::DOWN);

        // Calculate provider's share of TVL
        $tvl = $this->calculateTVL($pool);
        $providerTVL = $tvl->multipliedBy($shareRatio);

        // Get recent rewards
        $recentRewards = $this->getRecentProviderRewards($providerId, $poolId, 30); // 30 days

        // Calculate APY
        $dailyReturn = $providerTVL->isZero()
            ? BigDecimal::zero()
            : $recentRewards->dividedBy($providerTVL, 18, RoundingMode::DOWN)
            ->dividedBy(30, 18, RoundingMode::DOWN); // Average daily

        $apy = $dailyReturn->multipliedBy(365)->multipliedBy(100);

        // Calculate fee APY
        $fees24h = BigDecimal::of($pool->fees_collected_24h);
        $providerFees24h = $fees24h->multipliedBy($shareRatio);
        $feeAPY = $providerTVL->isZero()
            ? BigDecimal::zero()
            : $providerFees24h->dividedBy($providerTVL, 18, RoundingMode::DOWN)
            ->multipliedBy(365)
            ->multipliedBy(100);

        return [
            'provider_id' => $providerId,
            'pool_id'     => $poolId,
            'tvl'         => $providerTVL->__toString(),
            'reward_apy'  => $apy->__toString(),
            'fee_apy'     => $feeAPY->__toString(),
            'total_apy'   => $apy->plus($feeAPY)->__toString(),
            'share_ratio' => $shareRatio->multipliedBy(100)->toScale(0, RoundingMode::DOWN)->__toString() . '%',
        ];
    }

    /**
     * Get recent rewards for a provider.
     */
    private function getRecentProviderRewards(string $providerId, string $poolId, int $days): BigDecimal
    {
        // This would query reward distribution events
        // For now, return estimated rewards based on current rate
        $pool = PoolProjection::where('pool_id', $poolId)->first();
        $dailyRewards = $this->calculatePoolRewards($pool);

        $providerReward = BigDecimal::zero();
        foreach ($dailyRewards['provider_rewards'] as $reward) {
            if ($reward['provider_id'] === $providerId) {
                $providerReward = BigDecimal::of($reward['final_reward']);
                break;
            }
        }

        return $providerReward->multipliedBy($days);
    }
}
