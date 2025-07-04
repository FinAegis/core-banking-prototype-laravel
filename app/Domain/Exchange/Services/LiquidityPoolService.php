<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Aggregates\LiquidityPool;
use App\Domain\Exchange\Contracts\LiquidityPoolServiceInterface;
use App\Domain\Exchange\Projections\LiquidityPool as PoolProjection;
use App\Domain\Exchange\Projections\LiquidityProvider;
use App\Domain\Exchange\ValueObjects\LiquidityAdditionInput;
use App\Domain\Exchange\ValueObjects\LiquidityRemovalInput;
use App\Domain\Exchange\Workflows\LiquidityManagementWorkflow;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

class LiquidityPoolService implements LiquidityPoolServiceInterface
{
    public function __construct(
        private readonly ExchangeService $exchangeService
    ) {}

    /**
     * Create a new liquidity pool
     */
    public function createPool(
        string $baseCurrency,
        string $quoteCurrency,
        string $feeRate = '0.003',
        array $metadata = []
    ): string {
        // Check if pool already exists
        $existingPool = PoolProjection::forPair($baseCurrency, $quoteCurrency)->first();
        if ($existingPool) {
            throw new \DomainException('Liquidity pool already exists for this pair');
        }

        $poolId = Str::uuid()->toString();

        LiquidityPool::retrieve($poolId)
            ->createPool(
                poolId: $poolId,
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                feeRate: $feeRate,
                metadata: $metadata
            )
            ->persist();

        return $poolId;
    }

    /**
     * Add liquidity to a pool
     */
    public function addLiquidity(LiquidityAdditionInput $input): array
    {
        $workflow = WorkflowStub::make(LiquidityManagementWorkflow::class);
        return $workflow->addLiquidity($input);
    }

    /**
     * Remove liquidity from a pool
     */
    public function removeLiquidity(LiquidityRemovalInput $input): array
    {
        $workflow = WorkflowStub::make(LiquidityManagementWorkflow::class);
        return $workflow->removeLiquidity($input);
    }

    /**
     * Execute a swap through the pool
     */
    public function swap(
        string $poolId,
        string $accountId,
        string $inputCurrency,
        string $inputAmount,
        string $minOutputAmount = '0'
    ): array {
        $pool = LiquidityPool::retrieve($poolId);
        
        // Calculate swap details
        $swapDetails = $pool->executeSwap($inputCurrency, $inputAmount, $minOutputAmount);
        
        // Execute the actual asset transfers
        $this->exchangeService->executePoolSwap(
            poolId: $poolId,
            accountId: $accountId,
            inputCurrency: $inputCurrency,
            inputAmount: $inputAmount,
            outputCurrency: $swapDetails['outputCurrency'],
            outputAmount: $swapDetails['outputAmount'],
            feeAmount: $swapDetails['feeAmount']
        );

        return $swapDetails;
    }

    /**
     * Get pool details
     */
    public function getPool(string $poolId): ?PoolProjection
    {
        return PoolProjection::where('pool_id', $poolId)->first();
    }

    /**
     * Get pool by currency pair
     */
    public function getPoolByPair(string $baseCurrency, string $quoteCurrency): ?PoolProjection
    {
        return PoolProjection::forPair($baseCurrency, $quoteCurrency)->first();
    }

    /**
     * Get all active pools
     */
    public function getActivePools(): Collection
    {
        return PoolProjection::active()->get();
    }

    /**
     * Get provider's positions
     */
    public function getProviderPositions(string $providerId): Collection
    {
        return LiquidityProvider::where('provider_id', $providerId)
            ->with('pool')
            ->get();
    }

    /**
     * Calculate pool metrics
     */
    public function getPoolMetrics(string $poolId): array
    {
        $pool = PoolProjection::where('pool_id', $poolId)->firstOrFail();
        
        $baseReserve = BigDecimal::of($pool->base_reserve);
        $quoteReserve = BigDecimal::of($pool->quote_reserve);
        
        // Calculate TVL (Total Value Locked) in quote currency
        $spotPrice = $quoteReserve->dividedBy($baseReserve, 18);
        $baseValueInQuote = $baseReserve->multipliedBy($spotPrice);
        $tvl = $baseValueInQuote->plus($quoteReserve);
        
        // Calculate APY based on fees collected
        $feesCollected24h = BigDecimal::of($pool->fees_collected_24h);
        $dailyReturn = $tvl->isZero() ? BigDecimal::zero() : $feesCollected24h->dividedBy($tvl, 18);
        $apy = $dailyReturn->multipliedBy(365)->multipliedBy(100);

        return [
            'pool_id' => $poolId,
            'base_currency' => $pool->base_currency,
            'quote_currency' => $pool->quote_currency,
            'base_reserve' => $pool->base_reserve,
            'quote_reserve' => $pool->quote_reserve,
            'total_shares' => $pool->total_shares,
            'spot_price' => $spotPrice->__toString(),
            'tvl' => $tvl->__toString(),
            'volume_24h' => $pool->volume_24h,
            'fees_24h' => $pool->fees_collected_24h,
            'apy' => $apy->__toString(),
            'provider_count' => $pool->providers()->count(),
        ];
    }

    /**
     * Rebalance pool to target ratio
     */
    public function rebalancePool(string $poolId, string $targetRatio): array
    {
        $workflow = WorkflowStub::make(LiquidityManagementWorkflow::class);
        return $workflow->rebalancePool($poolId, $targetRatio);
    }

    /**
     * Distribute rewards to liquidity providers
     */
    public function distributeRewards(
        string $poolId,
        string $rewardAmount,
        string $rewardCurrency,
        array $metadata = []
    ): void {
        LiquidityPool::retrieve($poolId)
            ->distributeRewards($rewardAmount, $rewardCurrency, $metadata)
            ->persist();
    }

    /**
     * Claim rewards for a provider
     */
    public function claimRewards(string $poolId, string $providerId): array
    {
        $provider = LiquidityProvider::where('pool_id', $poolId)
            ->where('provider_id', $providerId)
            ->firstOrFail();

        $rewards = $provider->pending_rewards ?? [];
        
        if (empty($rewards)) {
            throw new \DomainException('No rewards to claim');
        }

        LiquidityPool::retrieve($poolId)
            ->claimRewards($providerId)
            ->persist();

        // Execute reward transfers
        foreach ($rewards as $currency => $amount) {
            $this->exchangeService->transferFromPool(
                poolId: $poolId,
                toAccountId: $providerId,
                currency: $currency,
                amount: $amount,
                description: 'Liquidity rewards claim'
            );
        }

        return $rewards;
    }

    /**
     * Update pool parameters
     */
    public function updatePoolParameters(
        string $poolId,
        ?string $feeRate = null,
        ?bool $isActive = null,
        array $metadata = []
    ): void {
        LiquidityPool::retrieve($poolId)
            ->updateParameters($feeRate, $isActive, $metadata)
            ->persist();
    }
}