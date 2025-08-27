<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Events\FeeTierUpdated;
use App\Domain\Exchange\Events\UserFeeTierAssigned;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Service for managing fee tiers based on trading volume and user status.
 * Implements a tiered fee structure with volume-based discounts.
 */
class FeeTierService
{
    private const CACHE_PREFIX = 'fee_tier:';

    private const CACHE_TTL = 3600; // 1 hour

    // Default fee tiers (in basis points)
    private const FEE_TIERS = [
        'retail' => [
            'name'       => 'Retail',
            'maker_fee'  => 30, // 0.30%
            'taker_fee'  => 40, // 0.40%
            'min_volume' => 0,
            'discount'   => 0,
        ],
        'bronze' => [
            'name'       => 'Bronze',
            'maker_fee'  => 25, // 0.25%
            'taker_fee'  => 35, // 0.35%
            'min_volume' => 10000, // $10k monthly volume
            'discount'   => 10, // 10% discount
        ],
        'silver' => [
            'name'       => 'Silver',
            'maker_fee'  => 20, // 0.20%
            'taker_fee'  => 30, // 0.30%
            'min_volume' => 50000, // $50k monthly volume
            'discount'   => 25, // 25% discount
        ],
        'gold' => [
            'name'       => 'Gold',
            'maker_fee'  => 15, // 0.15%
            'taker_fee'  => 25, // 0.25%
            'min_volume' => 250000, // $250k monthly volume
            'discount'   => 40, // 40% discount
        ],
        'platinum' => [
            'name'       => 'Platinum',
            'maker_fee'  => 10, // 0.10%
            'taker_fee'  => 20, // 0.20%
            'min_volume' => 1000000, // $1M monthly volume
            'discount'   => 50, // 50% discount
        ],
        'vip' => [
            'name'       => 'VIP',
            'maker_fee'  => 5, // 0.05%
            'taker_fee'  => 15, // 0.15%
            'min_volume' => 5000000, // $5M monthly volume
            'discount'   => 65, // 65% discount
        ],
    ];

    // Pool-specific fee tiers
    private const POOL_FEE_TIERS = [
        'stable'   => 5, // 0.05% for stable pairs (USDT/USDC)
        'standard' => 30, // 0.30% for standard pairs
        'exotic'   => 100, // 1.00% for exotic pairs
    ];

    /**
     * Get user's current fee tier based on trading volume.
     */
    public function getUserFeeTier(string $userId): array
    {
        $cacheKey = self::CACHE_PREFIX . "user:{$userId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            $monthlyVolume = $this->calculateUserMonthlyVolume($userId);
            $tier = $this->determineTierByVolume($monthlyVolume);

            // Check for special promotions or overrides
            $override = $this->getUserTierOverride($userId);
            if ($override) {
                $tier = self::FEE_TIERS[$override] ?? $tier;
            }

            return [
                'tier'           => $tier,
                'monthly_volume' => $monthlyVolume,
                'next_tier'      => $this->getNextTier($tier),
                'volume_to_next' => $this->getVolumeToNextTier($monthlyVolume),
            ];
        });
    }

    /**
     * Calculate fees for an order.
     */
    public function calculateOrderFees(
        string $userId,
        string $poolId,
        string $orderType,
        float $amount,
        float $price
    ): array {
        $userTier = $this->getUserFeeTier($userId);
        $poolTier = $this->getPoolFeeTier($poolId);

        // Determine if maker or taker
        $isMaker = $orderType === 'limit';
        $baseFee = $isMaker ? $userTier['tier']['maker_fee'] : $userTier['tier']['taker_fee'];

        // Apply pool-specific fee if lower
        $effectiveFee = min($baseFee, $poolTier);

        // Apply any promotional discounts
        $discount = $this->getActiveDiscounts($userId, $poolId);
        $finalFee = $effectiveFee * (1 - $discount);

        $orderValue = $amount * $price;
        $feeAmount = ($orderValue * $finalFee) / 10000; // Convert from basis points

        return [
            'fee_tier'          => $userTier['tier']['name'],
            'base_fee_bps'      => $baseFee,
            'effective_fee_bps' => $finalFee,
            'fee_amount'        => $feeAmount,
            'discount_applied'  => $discount * 100,
            'order_value'       => $orderValue,
        ];
    }

    /**
     * Get pool-specific fee tier.
     */
    public function getPoolFeeTier(string $poolId): float
    {
        $cacheKey = self::CACHE_PREFIX . "pool:{$poolId}";

        $result = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($poolId) {
            // Get pool details to determine pair type
            $pool = DB::table('liquidity_pools')
                ->where('pool_id', $poolId)
                ->first();

            if (! $pool) {
                return self::POOL_FEE_TIERS['standard'];
            }

            // Check if pool has a custom fee tier in metadata
            if ($pool->metadata) {
                $metadata = json_decode($pool->metadata, true);
                if (isset($metadata['fee_tier'])) {
                    return $metadata['fee_tier'];
                }
            }

            // Check if it's a stable pair
            $stableCoins = ['USDT', 'USDC', 'DAI', 'BUSD'];
            if (
                in_array($pool->base_currency, $stableCoins) &&
                in_array($pool->quote_currency, $stableCoins)
            ) {
                return self::POOL_FEE_TIERS['stable'];
            }

            // Check if it's an exotic pair
            $majorCurrencies = ['BTC', 'ETH', 'USDT', 'USDC'];
            if (
                ! in_array($pool->base_currency, $majorCurrencies) ||
                ! in_array($pool->quote_currency, $majorCurrencies)
            ) {
                return self::POOL_FEE_TIERS['exotic'];
            }

            return self::POOL_FEE_TIERS['standard'];
        });

        // Cast to float to ensure type consistency
        return (float) $result;
    }

    /**
     * Update user's fee tier manually (for VIP or promotional purposes).
     */
    public function assignUserFeeTier(string $userId, string $tierKey, ?string $reason = null): void
    {
        if (! isset(self::FEE_TIERS[$tierKey])) {
            throw new InvalidArgumentException("Invalid fee tier: {$tierKey}");
        }

        DB::table('user_fee_tiers')->updateOrInsert(
            ['user_id' => $userId],
            [
                'tier_override' => $tierKey,
                'reason'        => $reason,
                'assigned_at'   => now(),
                'expires_at'    => null, // Can be set for temporary promotions
            ]
        );

        // Clear cache
        Cache::forget(self::CACHE_PREFIX . "user:{$userId}");

        // Emit event
        event(new UserFeeTierAssigned(
            userId: $userId,
            tier: $tierKey,
            reason: $reason,
            timestamp: now()
        ));
    }

    /**
     * Calculate user's monthly trading volume.
     */
    private function calculateUserMonthlyVolume(string $userId): float
    {
        $startOfMonth = now()->startOfMonth();

        return (float) DB::table('orders')
            ->where('user_id', $userId)
            ->where('status', 'executed')
            ->where('executed_at', '>=', $startOfMonth)
            ->sum(DB::raw('amount * price'));
    }

    /**
     * Determine tier based on volume.
     */
    private function determineTierByVolume(float $volume): array
    {
        $selectedTier = self::FEE_TIERS['retail'];

        foreach (self::FEE_TIERS as $tier) {
            if ($volume >= $tier['min_volume']) {
                $selectedTier = $tier;
            }
        }

        return $selectedTier;
    }

    /**
     * Get user's tier override if exists.
     */
    private function getUserTierOverride(string $userId): ?string
    {
        $override = DB::table('user_fee_tiers')
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->value('tier_override');

        return $override;
    }

    /**
     * Get next tier information.
     */
    private function getNextTier(array $currentTier): ?array
    {
        $tiers = array_values(self::FEE_TIERS);
        $currentIndex = array_search($currentTier, $tiers);

        if ($currentIndex === false || $currentIndex === count($tiers) - 1) {
            return null;
        }

        return $tiers[$currentIndex + 1];
    }

    /**
     * Calculate volume needed to reach next tier.
     */
    private function getVolumeToNextTier(float $currentVolume): ?float
    {
        $currentTier = $this->determineTierByVolume($currentVolume);
        $nextTier = $this->getNextTier($currentTier);

        if (! $nextTier) {
            return null;
        }

        return max(0, $nextTier['min_volume'] - $currentVolume);
    }

    /**
     * Get active discounts for user/pool combination.
     */
    private function getActiveDiscounts(string $userId, string $poolId): float
    {
        // Check for user-specific promotions
        $userDiscount = DB::table('promotions')
            ->where('user_id', $userId)
            ->where('active', true)
            ->where('expires_at', '>', now())
            ->max('discount_rate') ?? 0;

        // Check for pool-specific promotions
        $poolDiscount = DB::table('pool_promotions')
            ->where('pool_id', $poolId)
            ->where('active', true)
            ->where('expires_at', '>', now())
            ->max('discount_rate') ?? 0;

        // Return the best discount available
        return max($userDiscount, $poolDiscount);
    }

    /**
     * Update pool fee tier.
     */
    public function updatePoolFeeTier(string $poolId, float $newFeeBps): void
    {
        // Get old fee before updating
        $oldFee = $this->getPoolFeeTier($poolId);

        // Get the pool and update metadata
        $pool = DB::table('liquidity_pools')
            ->where('pool_id', $poolId)
            ->first();

        if ($pool) {
            $metadata = json_decode($pool->metadata ?? '{}', true);
            $metadata['fee_tier'] = $newFeeBps;

            DB::table('liquidity_pools')
                ->where('pool_id', $poolId)
                ->update([
                    'metadata'   => json_encode($metadata),
                    'updated_at' => now(),
                ]);
        }

        // Clear cache
        Cache::forget(self::CACHE_PREFIX . "pool:{$poolId}");

        // Emit event
        event(new FeeTierUpdated(
            poolId: $poolId,
            oldFee: $oldFee,
            newFee: $newFeeBps,
            timestamp: now()
        ));
    }

    /**
     * Get fee statistics for reporting.
     */
    public function getFeeStatistics(string $period = 'monthly'): array
    {
        $startDate = match ($period) {
            'daily'   => now()->startOfDay(),
            'weekly'  => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            default   => now()->startOfMonth(),
        };

        $stats = DB::table('orders')
            ->where('executed_at', '>=', $startDate)
            ->where('status', 'executed')
            ->selectRaw('
                COUNT(*) as total_orders,
                SUM(fee_amount) as total_fees,
                AVG(fee_amount) as avg_fee,
                SUM(amount * price) as total_volume
            ')
            ->first();

        $tierDistribution = DB::table('user_fee_tiers')
            ->selectRaw('tier_override, COUNT(*) as count')
            ->groupBy('tier_override')
            ->pluck('count', 'tier_override')
            ->toArray();

        return [
            'period'             => $period,
            'total_orders'       => $stats->total_orders ?? 0,
            'total_fees'         => $stats->total_fees ?? 0,
            'average_fee'        => $stats->avg_fee ?? 0,
            'total_volume'       => $stats->total_volume ?? 0,
            'tier_distribution'  => $tierDistribution,
            'effective_fee_rate' => $stats->total_volume > 0
                ? ($stats->total_fees / $stats->total_volume) * 10000
                : 0,
        ];
    }
}
