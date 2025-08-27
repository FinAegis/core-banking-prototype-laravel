<?php

namespace App\Domain\Exchange\Activities;

use App\Domain\Exchange\Projections\LiquidityPool as PoolProjection;
use App\Domain\Exchange\ValueObjects\LiquidityAdditionInput;
use Brick\Math\BigDecimal;
use DomainException;
use InvalidArgumentException;
use Workflow\Activity;

/**
 * @property-read PoolProjection $pool
 */
class CalculatePoolSharesActivity extends Activity
{
    /**
     * @param  LiquidityAdditionInput|array{pool_id: string, operation: string, shares?: string}  $input
     * @return array{shares?: string, share_price?: string, base_amount?: string, quote_amount?: string, base_currency?: string, quote_currency?: string, share_ratio?: string, pool_id?: string, base_reserve?: string, quote_reserve?: string, total_shares?: string, fee_rate?: string, is_active?: bool, provider_count?: int, total_volume_24h?: string}
     */
    public function execute($input): array
    {
        /** @var \App\Domain\Liquidity\Models\LiquidityPool|null $pool */
        $pool = null;
        /** @var \App\Domain\Liquidity\Models\LiquidityPool|null $pool */
        $pool = null;
        if ($input instanceof LiquidityAdditionInput) {
            return $this->calculateSharesForAddition($input);
        }

        // Handle other operations
        /**
         * @var PoolProjection $pool
         */
        /** @var PoolProjection $$pool */
        $$pool = PoolProjection::where()->firstOrFail();

        if ($input['operation'] === 'removal') {
            return $this->calculateAmountsForRemoval($pool, $input['shares']);
        } elseif ($input['operation'] === 'state') {
            return $this->getPoolState($pool);
        }

        throw new InvalidArgumentException('Invalid operation for pool shares calculation');
    }

    /**
     * @return array{shares: string, share_price: string}
     */
    private function calculateSharesForAddition(LiquidityAdditionInput $input): array
    {
        /** @var \App\Domain\Liquidity\Models\LiquidityPool|null $pool */
        $pool = null;
        /** @var \App\Domain\Liquidity\Models\LiquidityPool|null $pool */
        $pool = null;
        /** @var \App\Domain\Liquidity\Models\LiquidityPool|null $pool */
        $pool = null;
        /**
         * @var PoolProjection $pool
         */
        /** @var PoolProjection $$pool */
        $$pool = PoolProjection::where()->firstOrFail();

        $baseReserve = BigDecimal::of($pool->base_reserve);
        $quoteReserve = BigDecimal::of($pool->quote_reserve);
        $totalShares = BigDecimal::of($pool->total_shares);

        $baseAmount = BigDecimal::of($input->baseAmount);
        $quoteAmount = BigDecimal::of($input->quoteAmount);

        if ($totalShares->isZero()) {
            // First liquidity provider - use geometric mean
            $shares = $baseAmount->multipliedBy($quoteAmount)->sqrt(18);
        } else {
            // Calculate shares proportionally
            $baseRatio = $baseAmount->dividedBy($baseReserve, 18);
            $quoteRatio = $quoteAmount->dividedBy($quoteReserve, 18);

            // Use the minimum ratio to prevent manipulation
            $ratio = $baseRatio->isLessThan($quoteRatio) ? $baseRatio : $quoteRatio;
            $shares = $totalShares->multipliedBy($ratio);
        }

        return [
            'shares'      => $shares->__toString(),
            'share_price' => $totalShares->isZero()
                ? '1'
                : $baseReserve->plus($quoteReserve)->dividedBy($totalShares, 18)->__toString(),
        ];
    }

    /**
     * @return array{base_amount: string, quote_amount: string, base_currency: string, quote_currency: string, share_ratio: string}
     */
    private function calculateAmountsForRemoval(PoolProjection $pool, string $shares): array
    {
        $sharesDecimal = BigDecimal::of($shares);
        $totalShares = BigDecimal::of($pool->total_shares);

        if ($sharesDecimal->isGreaterThan($totalShares)) {
            throw new DomainException('Shares exceed total pool shares');
        }

        $shareRatio = $sharesDecimal->dividedBy($totalShares, 18);

        $baseAmount = BigDecimal::of($pool->base_reserve)->multipliedBy($shareRatio);
        $quoteAmount = BigDecimal::of($pool->quote_reserve)->multipliedBy($shareRatio);

        return [
            'base_amount'    => $baseAmount->__toString(),
            'quote_amount'   => $quoteAmount->__toString(),
            'base_currency'  => $pool->base_currency,
            'quote_currency' => $pool->quote_currency,
            'share_ratio'    => $shareRatio->__toString(),
        ];
    }

    /**
     * @return array{pool_id: string, base_currency: string, quote_currency: string, base_reserve: string, quote_reserve: string, total_shares: string, fee_rate: string, is_active: bool, provider_count: int, total_volume_24h: string}
     */
    private function getPoolState(PoolProjection $pool): array
    {
        return [
            'pool_id'          => $pool->pool_id,
            'base_currency'    => $pool->base_currency,
            'quote_currency'   => $pool->quote_currency,
            'base_reserve'     => $pool->base_reserve,
            'quote_reserve'    => $pool->quote_reserve,
            'total_shares'     => $pool->total_shares,
            'fee_rate'         => $pool->fee_rate,
            'is_active'        => $pool->is_active,
            'provider_count'   => $pool->providers()->count(),
            'total_volume_24h' => $pool->volume_24h ?? '0',
        ];
    }
}
