<?php

declare(strict_types=1);

namespace App\Domain\Exchange\LiquidityPool\Services;

use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Exchange\Projections\LiquidityProvider;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;

class ImpermanentLossProtectionService
{
    /**
     * Protection threshold - providers are compensated for IL above this percentage.
     */
    private const PROTECTION_THRESHOLD = '0.02'; // 2% IL threshold

    /**
     * Maximum protection coverage percentage.
     */
    private const MAX_PROTECTION_COVERAGE = '0.80'; // 80% of IL covered

    /**
     * Minimum holding period for protection eligibility (in hours).
     */
    private const MIN_HOLDING_PERIOD = 168; // 7 days

    /**
     * Calculate impermanent loss for a liquidity position.
     */
    public function calculateImpermanentLoss(
        LiquidityProvider $position,
        BigDecimal $currentBasePrice
    ): array {
        // Get entry price from metadata or calculate from initial amounts
        $metadata = $position->metadata ?? [];
        $entryBasePrice = isset($metadata['entry_base_price'])
            ? BigDecimal::of($metadata['entry_base_price'])
            : BigDecimal::of($position->initial_quote_amount)->dividedBy($position->initial_base_amount, 18, RoundingMode::HALF_UP);

        $baseAmount = BigDecimal::of($position->initial_base_amount);
        $quoteAmount = BigDecimal::of($position->initial_quote_amount);

        // Calculate price ratio
        $priceRatio = $currentBasePrice->dividedBy($entryBasePrice, 18, RoundingMode::HALF_UP);

        // Calculate theoretical value if held (no LP)
        $holdValue = $baseAmount->multipliedBy($currentBasePrice)
            ->plus($quoteAmount);

        // Calculate actual LP position value
        // Value = 2 * sqrt(base_amount * quote_amount * current_price)
        $k = $baseAmount->multipliedBy($quoteAmount);
        $sqrtK = $k->sqrt(18);
        $sqrtPrice = $currentBasePrice->sqrt(18);
        $lpValue = BigDecimal::of('2')->multipliedBy($sqrtK)->multipliedBy($sqrtPrice);

        // Calculate impermanent loss
        $impermanentLoss = $holdValue->minus($lpValue);
        $impermanentLossPercent = $holdValue->isZero()
            ? BigDecimal::zero()
            : $impermanentLoss->dividedBy($holdValue, 18, RoundingMode::HALF_UP)
                ->multipliedBy('100');

        return [
            'position_id'              => $position->id ?? $position->provider_id,
            'entry_price'              => $entryBasePrice->__toString(),
            'current_price'            => $currentBasePrice->__toString(),
            'price_ratio'              => $priceRatio->__toString(),
            'hold_value'               => $holdValue->__toString(),
            'lp_value'                 => $lpValue->__toString(),
            'impermanent_loss'         => $impermanentLoss->__toString(),
            'impermanent_loss_percent' => $impermanentLossPercent->__toString(),
            'is_protected'             => $this->isEligibleForProtection($position),
        ];
    }

    /**
     * Calculate protection compensation for eligible positions.
     */
    public function calculateProtectionCompensation(
        LiquidityProvider $position,
        BigDecimal $impermanentLoss,
        BigDecimal $impermanentLossPercent
    ): array {
        if (! $this->isEligibleForProtection($position)) {
            return [
                'eligible'     => false,
                'reason'       => 'Position not eligible for IL protection',
                'compensation' => '0',
            ];
        }

        $threshold = BigDecimal::of(self::PROTECTION_THRESHOLD)->multipliedBy('100');

        // Only compensate for IL above threshold
        if ($impermanentLossPercent->isLessThanOrEqualTo($threshold)) {
            return [
                'eligible'     => false,
                'reason'       => 'Impermanent loss below protection threshold',
                'threshold'    => $threshold->__toString() . '%',
                'actual_loss'  => $impermanentLossPercent->__toString() . '%',
                'compensation' => '0',
            ];
        }

        // Calculate protection coverage based on holding period
        $coverageRate = $this->calculateCoverageRate($position);

        // Calculate compensation amount
        $excessLoss = $impermanentLossPercent->minus($threshold);
        $coveredLossPercent = $excessLoss->multipliedBy($coverageRate);

        // Convert percentage to actual compensation amount
        // Use initial amounts for position value calculation
        $pool = $position->pool;
        if (! $pool) {
            return [
                'eligible'     => false,
                'reason'       => 'Pool not found for position',
                'compensation' => '0',
            ];
        }
        $currentPrice = $this->getCurrentPoolPrice($pool);
        $positionValue = BigDecimal::of($position->initial_base_amount)
            ->multipliedBy($currentPrice)
            ->plus($position->initial_quote_amount);

        $compensation = $positionValue
            ->multipliedBy($coveredLossPercent)
            ->dividedBy('100', 18, RoundingMode::HALF_UP);

        return [
            'eligible'                 => true,
            'impermanent_loss'         => $impermanentLoss->__toString(),
            'impermanent_loss_percent' => $impermanentLossPercent->__toString() . '%',
            'threshold'                => $threshold->__toString() . '%',
            'excess_loss'              => $excessLoss->__toString() . '%',
            'coverage_rate'            => $coverageRate->multipliedBy('100')->__toString() . '%',
            'compensation'             => $compensation->__toString(),
            'compensation_currency'    => $position->pool->quote_currency,
        ];
    }

    /**
     * Process IL protection claims for all eligible positions in a pool.
     */
    public function processProtectionClaims(string $poolId): Collection
    {
        $pool = LiquidityPool::where('pool_id', $poolId)->firstOrFail();
        $positions = LiquidityProvider::where('pool_id', $poolId)
            ->where('shares', '>', 0)
            ->get();

        $claims = collect();
        $currentBasePrice = $this->getCurrentPoolPrice($pool);

        foreach ($positions as $position) {
            $ilData = $this->calculateImpermanentLoss($position, $currentBasePrice);

            if (! $ilData['is_protected']) {
                continue;
            }

            $compensation = $this->calculateProtectionCompensation(
                $position,
                BigDecimal::of($ilData['impermanent_loss']),
                BigDecimal::of($ilData['impermanent_loss_percent'])
            );

            if ($compensation['eligible']) {
                $claims->push([
                    'provider_id'           => $position->provider_id,
                    'position_id'           => $position->id,
                    'pool_id'               => $poolId,
                    'impermanent_loss'      => $compensation['impermanent_loss'],
                    'compensation'          => $compensation['compensation'],
                    'compensation_currency' => $compensation['compensation_currency'],
                    'processed_at'          => now(),
                ]);
            }
        }

        return $claims;
    }

    /**
     * Check if a position is eligible for IL protection.
     */
    public function isEligibleForProtection(LiquidityProvider $position): bool
    {
        // Check minimum holding period
        $holdingHours = $position->created_at->diffInHours(now());
        if ($holdingHours < self::MIN_HOLDING_PERIOD) {
            return false;
        }

        // Check if position is still active (has shares)
        if (BigDecimal::of($position->shares ?? '0')->isLessThanOrEqualTo(0)) {
            return false;
        }

        // Check if pool has protection enabled
        // Load pool fresh if not already loaded
        $pool = $position->relationLoaded('pool') ? $position->pool : $position->load('pool')->pool;
        if (! $pool || ! ($pool->metadata['il_protection_enabled'] ?? false)) {
            return false;
        }

        return true;
    }

    /**
     * Calculate coverage rate based on holding period
     * Longer holding periods get better coverage.
     */
    private function calculateCoverageRate(LiquidityProvider $position): BigDecimal
    {
        $holdingDays = $position->created_at->diffInDays(now());

        // Coverage increases with holding period
        // 7 days: 20% coverage
        // 30 days: 40% coverage
        // 90 days: 60% coverage
        // 180+ days: 80% coverage (max)

        if ($holdingDays < 7) {
            return BigDecimal::zero();
        } elseif ($holdingDays < 30) {
            return BigDecimal::of('0.20');
        } elseif ($holdingDays < 90) {
            return BigDecimal::of('0.40');
        } elseif ($holdingDays < 180) {
            return BigDecimal::of('0.60');
        } else {
            return BigDecimal::of(self::MAX_PROTECTION_COVERAGE);
        }
    }

    /**
     * Get current pool price (quote per base).
     */
    private function getCurrentPoolPrice(LiquidityPool $pool): BigDecimal
    {
        $baseReserve = BigDecimal::of($pool->base_reserve);
        $quoteReserve = BigDecimal::of($pool->quote_reserve);

        if ($baseReserve->isZero()) {
            return BigDecimal::zero();
        }

        return $quoteReserve->dividedBy($baseReserve, 18, RoundingMode::HALF_UP);
    }

    /**
     * Estimate IL protection fund requirements for a pool.
     */
    public function estimateProtectionFundRequirements(string $poolId): array
    {
        $pool = LiquidityPool::where('pool_id', $poolId)->firstOrFail();
        $positions = LiquidityProvider::where('pool_id', $poolId)
            ->where('shares', '>', 0)
            ->get();

        $totalValue = BigDecimal::zero();
        $protectedValue = BigDecimal::zero();
        $maxPotentialCompensation = BigDecimal::zero();
        $currentPrice = $this->getCurrentPoolPrice($pool);

        foreach ($positions as $position) {
            $positionValue = BigDecimal::of($position->initial_base_amount ?? '0')
                ->multipliedBy($currentPrice)
                ->plus($position->initial_quote_amount ?? '0');

            $totalValue = $totalValue->plus($positionValue);

            if ($this->isEligibleForProtection($position)) {
                $protectedValue = $protectedValue->plus($positionValue);

                // Estimate max potential compensation (worst case scenario)
                // Assume 50% IL with max coverage
                $maxCompensation = $positionValue
                    ->multipliedBy('0.50') // 50% IL
                    ->multipliedBy(self::MAX_PROTECTION_COVERAGE);

                $maxPotentialCompensation = $maxPotentialCompensation->plus($maxCompensation);
            }
        }

        // Recommended fund size is 10% of max potential compensation
        $recommendedFundSize = $maxPotentialCompensation->multipliedBy('0.10');

        return [
            'pool_id'               => $poolId,
            'total_liquidity_value' => $totalValue->__toString(),
            'protected_value'       => $protectedValue->__toString(),
            'protected_percentage'  => $totalValue->isZero()
                ? '0'
                : $protectedValue->dividedBy($totalValue, 4, RoundingMode::HALF_UP)
                    ->multipliedBy('100')->__toString() . '%',
            'max_potential_compensation' => $maxPotentialCompensation->__toString(),
            'recommended_fund_size'      => $recommendedFundSize->__toString(),
            'fund_currency'              => $pool->quote_currency,
        ];
    }
}
