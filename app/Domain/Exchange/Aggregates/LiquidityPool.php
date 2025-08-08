<?php

namespace App\Domain\Exchange\Aggregates;

use App\Domain\Exchange\Events\ImpermanentLossProtectionClaimed;
use App\Domain\Exchange\Events\ImpermanentLossProtectionEnabled;
use App\Domain\Exchange\Events\LiquidityAdded;
use App\Domain\Exchange\Events\LiquidityPoolCreated;
use App\Domain\Exchange\Events\LiquidityPoolRebalanced;
use App\Domain\Exchange\Events\LiquidityRemoved;
use App\Domain\Exchange\Events\LiquidityRewardsClaimed;
use App\Domain\Exchange\Events\LiquidityRewardsDistributed;
use App\Domain\Exchange\Events\PoolFeeCollected;
use App\Domain\Exchange\Events\PoolParametersUpdated;
use App\Domain\Exchange\LiquidityPool\Repositories\LiquidityPoolEventRepository;
use App\Domain\Exchange\LiquidityPool\Repositories\LiquidityPoolSnapshotRepository;
use Brick\Math\BigDecimal;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class LiquidityPool extends AggregateRoot
{
    private ?string $poolId = null;

    private ?string $baseCurrency = null;

    private ?string $quoteCurrency = null;

    private BigDecimal $baseReserve;

    private BigDecimal $quoteReserve;

    private BigDecimal $totalShares;

    private array $providers = [];

    private BigDecimal $feeRate;

    private bool $isActive = false;

    private array $metadata = [];

    public function __construct()
    {
        $this->baseReserve = BigDecimal::zero();
        $this->quoteReserve = BigDecimal::zero();
        $this->totalShares = BigDecimal::zero();
        $this->feeRate = BigDecimal::of('0.003'); // 0.3% default
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getStoredEventRepository(): LiquidityPoolEventRepository
    {
        return app()->make(
            abstract: LiquidityPoolEventRepository::class
        );
    }

    /**
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getSnapshotRepository(): LiquidityPoolSnapshotRepository
    {
        return app()->make(
            abstract: LiquidityPoolSnapshotRepository::class
        );
    }

    public function createPool(
        string $poolId,
        string $baseCurrency,
        string $quoteCurrency,
        string $feeRate = '0.003',
        array $metadata = []
    ): self {
        if ($this->poolId !== null) {
            throw new \DomainException('Pool already created');
        }

        $this->recordThat(
            new LiquidityPoolCreated(
                poolId: $poolId,
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                feeRate: $feeRate,
                metadata: $metadata
            )
        );

        return $this;
    }

    public function addLiquidity(
        string $providerId,
        string $baseAmount,
        string $quoteAmount,
        string $minShares = '0',
        array $metadata = []
    ): self {
        if (! $this->isActive) {
            throw new \DomainException('Pool is not active');
        }

        $baseAmountDecimal = BigDecimal::of($baseAmount);
        $quoteAmountDecimal = BigDecimal::of($quoteAmount);

        // Validate ratio for subsequent liquidity additions
        if (! $this->totalShares->isZero()) {
            $currentRatio = $this->quoteReserve->dividedBy($this->baseReserve, 18, \Brick\Math\RoundingMode::DOWN);
            $inputRatio = $quoteAmountDecimal->dividedBy($baseAmountDecimal, 18, \Brick\Math\RoundingMode::DOWN);

            // Allow 1% deviation
            $tolerance = BigDecimal::of('0.01');
            $deviation = $inputRatio->minus($currentRatio)->abs()->dividedBy($currentRatio, 18, \Brick\Math\RoundingMode::UP);

            if ($deviation->isGreaterThan($tolerance)) {
                throw new \DomainException('Input amounts deviate too much from pool ratio');
            }
        }

        // Calculate shares to mint
        $shares = $this->calculateSharesForLiquidity($baseAmountDecimal, $quoteAmountDecimal);

        if ($shares->isLessThan($minShares)) {
            throw new \DomainException('Slippage tolerance exceeded');
        }

        $this->recordThat(
            new LiquidityAdded(
                poolId: $this->poolId,
                providerId: $providerId,
                baseAmount: $baseAmount,
                quoteAmount: $quoteAmount,
                sharesMinted: $shares->__toString(),
                newBaseReserve: $this->baseReserve->plus($baseAmountDecimal)->__toString(),
                newQuoteReserve: $this->quoteReserve->plus($quoteAmountDecimal)->__toString(),
                newTotalShares: $this->totalShares->plus($shares)->__toString(),
                metadata: $metadata
            )
        );

        return $this;
    }

    public function removeLiquidity(
        string $providerId,
        string $shares,
        string $minBaseAmount = '0',
        string $minQuoteAmount = '0',
        array $metadata = []
    ): self {
        if (! $this->isActive) {
            throw new \DomainException('Pool is not active');
        }

        $sharesDecimal = BigDecimal::of($shares);
        $providerShares = BigDecimal::of($this->providers[$providerId]['shares'] ?? '0');

        if ($providerShares->isLessThan($sharesDecimal)) {
            throw new \DomainException('Insufficient shares');
        }

        // Calculate amounts to return
        $shareRatio = $sharesDecimal->dividedBy($this->totalShares, 18, \Brick\Math\RoundingMode::DOWN);
        $baseAmount = $this->baseReserve->multipliedBy($shareRatio)->toScale(8, \Brick\Math\RoundingMode::DOWN);
        $quoteAmount = $this->quoteReserve->multipliedBy($shareRatio)->toScale(2, \Brick\Math\RoundingMode::DOWN);

        if ($baseAmount->isLessThan($minBaseAmount) || $quoteAmount->isLessThan($minQuoteAmount)) {
            throw new \DomainException('Slippage tolerance exceeded');
        }

        $this->recordThat(
            new LiquidityRemoved(
                poolId: $this->poolId,
                providerId: $providerId,
                sharesBurned: $shares,
                baseAmount: $baseAmount->__toString(),
                quoteAmount: $quoteAmount->__toString(),
                newBaseReserve: $this->baseReserve->minus($baseAmount)->__toString(),
                newQuoteReserve: $this->quoteReserve->minus($quoteAmount)->__toString(),
                newTotalShares: $this->totalShares->minus($sharesDecimal)->__toString(),
                metadata: $metadata
            )
        );

        return $this;
    }

    public function executeSwap(
        string $inputCurrency,
        string $inputAmount,
        string $minOutputAmount = '0'
    ): array {
        if (! $this->isActive) {
            throw new \DomainException('Pool is not active');
        }

        $inputAmountDecimal = BigDecimal::of($inputAmount);
        $isBaseInput = $inputCurrency === $this->baseCurrency;

        // Calculate output using constant product formula (x * y = k)
        if ($isBaseInput) {
            $outputAmount = $this->calculateOutputAmount(
                $inputAmountDecimal,
                $this->baseReserve,
                $this->quoteReserve
            );
            $outputCurrency = $this->quoteCurrency;
        } else {
            $outputAmount = $this->calculateOutputAmount(
                $inputAmountDecimal,
                $this->quoteReserve,
                $this->baseReserve
            );
            $outputCurrency = $this->baseCurrency;
        }

        if ($outputAmount->isLessThan($minOutputAmount)) {
            throw new \DomainException('Slippage tolerance exceeded');
        }

        // Collect fee
        $feeAmount = $inputAmountDecimal->multipliedBy($this->feeRate);

        $this->recordThat(
            new PoolFeeCollected(
                poolId: $this->poolId,
                currency: $inputCurrency,
                feeAmount: $feeAmount->__toString(),
                swapVolume: $inputAmount,
                metadata: ['output_amount' => $outputAmount->__toString()]
            )
        );

        return [
            'outputAmount'   => $outputAmount->__toString(),
            'outputCurrency' => $outputCurrency,
            'feeAmount'      => $feeAmount->__toString(),
            'priceImpact'    => $this->calculatePriceImpact($inputAmountDecimal, $isBaseInput)->__toString(),
        ];
    }

    public function rebalancePool(
        string $targetRatio,
        string $maxSlippage = '0.01',
        array $metadata = []
    ): self {
        if (! $this->isActive) {
            throw new \DomainException('Pool is not active');
        }

        $targetRatioDecimal = BigDecimal::of($targetRatio);
        $currentRatio = $this->baseReserve->dividedBy($this->quoteReserve, 18);

        $deviation = $currentRatio->minus($targetRatioDecimal)->abs()
            ->dividedBy($targetRatioDecimal, 18);

        if ($deviation->isGreaterThan($maxSlippage)) {
            $this->recordThat(
                new LiquidityPoolRebalanced(
                    poolId: $this->poolId,
                    oldRatio: $currentRatio->__toString(),
                    newRatio: $targetRatio,
                    rebalanceAmount: '0', // Calculated by workflow
                    rebalanceCurrency: '', // Determined by workflow
                    metadata: $metadata
                )
            );
        }

        return $this;
    }

    public function distributeRewards(
        string $rewardAmount,
        string $rewardCurrency,
        array $metadata = []
    ): self {
        if ($this->totalShares->isZero()) {
            throw new \DomainException('No liquidity providers to distribute rewards to');
        }

        $this->recordThat(
            new LiquidityRewardsDistributed(
                poolId: $this->poolId,
                rewardAmount: $rewardAmount,
                rewardCurrency: $rewardCurrency,
                totalShares: $this->totalShares->__toString(),
                metadata: $metadata
            )
        );

        return $this;
    }

    public function claimRewards(
        string $providerId,
        array $metadata = []
    ): self {
        $provider = $this->providers[$providerId] ?? null;
        if (! $provider) {
            throw new \DomainException('Provider not found in pool');
        }

        $pendingRewards = $provider['pending_rewards'] ?? [];
        if (empty($pendingRewards)) {
            throw new \DomainException('No rewards to claim');
        }

        $this->recordThat(
            new LiquidityRewardsClaimed(
                poolId: $this->poolId,
                providerId: $providerId,
                rewards: $pendingRewards,
                metadata: $metadata
            )
        );

        return $this;
    }

    public function updateParameters(
        ?string $feeRate = null,
        ?bool $isActive = null,
        array $metadata = []
    ): self {
        $changes = [];

        if ($feeRate !== null) {
            $changes['fee_rate'] = $feeRate;
        }

        if ($isActive !== null) {
            $changes['is_active'] = $isActive;
        }

        if (! empty($changes)) {
            $this->recordThat(
                new PoolParametersUpdated(
                    poolId: $this->poolId,
                    changes: $changes,
                    metadata: $metadata
                )
            );
        }

        return $this;
    }

    private function calculateSharesForLiquidity(
        BigDecimal $baseAmount,
        BigDecimal $quoteAmount
    ): BigDecimal {
        if ($this->totalShares->isZero()) {
            // First liquidity provider - use geometric mean
            return $baseAmount->multipliedBy($quoteAmount)->sqrt(18);
        }

        // Calculate shares proportionally
        $baseRatio = $baseAmount->dividedBy($this->baseReserve, 18, \Brick\Math\RoundingMode::DOWN);
        $quoteRatio = $quoteAmount->dividedBy($this->quoteReserve, 18, \Brick\Math\RoundingMode::DOWN);

        // Use the minimum ratio to prevent manipulation
        $ratio = $baseRatio->isLessThan($quoteRatio) ? $baseRatio : $quoteRatio;

        return $this->totalShares->multipliedBy($ratio);
    }

    private function calculateOutputAmount(
        BigDecimal $inputAmount,
        BigDecimal $inputReserve,
        BigDecimal $outputReserve
    ): BigDecimal {
        // Apply fee
        $inputWithFee = $inputAmount->multipliedBy(BigDecimal::one()->minus($this->feeRate));

        // Constant product formula: (x + dx) * (y - dy) = x * y
        // dy = y * dx / (x + dx)
        $numerator = $outputReserve->multipliedBy($inputWithFee);
        $denominator = $inputReserve->plus($inputWithFee);

        return $numerator->dividedBy($denominator, 18);
    }

    private function calculatePriceImpact(BigDecimal $inputAmount, bool $isBaseInput): BigDecimal
    {
        $spotPrice = $isBaseInput
            ? $this->quoteReserve->dividedBy($this->baseReserve, 18)
            : $this->baseReserve->dividedBy($this->quoteReserve, 18);

        $outputAmount = $isBaseInput
            ? $this->calculateOutputAmount($inputAmount, $this->baseReserve, $this->quoteReserve)
            : $this->calculateOutputAmount($inputAmount, $this->quoteReserve, $this->baseReserve);

        $executionPrice = $outputAmount->dividedBy($inputAmount, 18);

        return $spotPrice->minus($executionPrice)
            ->dividedBy($spotPrice, 18)
            ->abs()
            ->multipliedBy(100);
    }

    // Event handlers
    protected function applyLiquidityPoolCreated(LiquidityPoolCreated $event): void
    {
        $this->poolId = $event->poolId;
        $this->baseCurrency = $event->baseCurrency;
        $this->quoteCurrency = $event->quoteCurrency;
        $this->feeRate = BigDecimal::of($event->feeRate);
        $this->isActive = true;
        $this->metadata = $event->metadata;
    }

    protected function applyLiquidityAdded(LiquidityAdded $event): void
    {
        $this->baseReserve = BigDecimal::of($event->newBaseReserve);
        $this->quoteReserve = BigDecimal::of($event->newQuoteReserve);
        $this->totalShares = BigDecimal::of($event->newTotalShares);

        if (! isset($this->providers[$event->providerId])) {
            $this->providers[$event->providerId] = [
                'shares'          => '0',
                'pending_rewards' => [],
            ];
        }

        $currentShares = BigDecimal::of($this->providers[$event->providerId]['shares']);
        $this->providers[$event->providerId]['shares'] = $currentShares
            ->plus($event->sharesMinted)
            ->__toString();
    }

    protected function applyLiquidityRemoved(LiquidityRemoved $event): void
    {
        $this->baseReserve = BigDecimal::of($event->newBaseReserve);
        $this->quoteReserve = BigDecimal::of($event->newQuoteReserve);
        $this->totalShares = BigDecimal::of($event->newTotalShares);

        $currentShares = BigDecimal::of($this->providers[$event->providerId]['shares']);
        $this->providers[$event->providerId]['shares'] = $currentShares
            ->minus($event->sharesBurned)
            ->__toString();
    }

    protected function applyPoolParametersUpdated(PoolParametersUpdated $event): void
    {
        if (isset($event->changes['fee_rate'])) {
            $this->feeRate = BigDecimal::of($event->changes['fee_rate']);
        }

        if (isset($event->changes['is_active'])) {
            $this->isActive = $event->changes['is_active'];
        }
    }

    protected function applyLiquidityRewardsDistributed(LiquidityRewardsDistributed $event): void
    {
        // Calculate each provider's share of rewards
        foreach ($this->providers as $providerId => &$provider) {
            $providerShares = BigDecimal::of($provider['shares']);
            if ($providerShares->isGreaterThan(0)) {
                $shareRatio = $providerShares->dividedBy($this->totalShares, 18);
                $providerReward = BigDecimal::of($event->rewardAmount)->multipliedBy($shareRatio);

                if (! isset($provider['pending_rewards'][$event->rewardCurrency])) {
                    $provider['pending_rewards'][$event->rewardCurrency] = '0';
                }

                $currentRewards = BigDecimal::of($provider['pending_rewards'][$event->rewardCurrency]);
                $provider['pending_rewards'][$event->rewardCurrency] = $currentRewards
                    ->plus($providerReward)
                    ->__toString();
            }
        }
    }

    protected function applyLiquidityRewardsClaimed(LiquidityRewardsClaimed $event): void
    {
        $this->providers[$event->providerId]['pending_rewards'] = [];
    }

    public function enableImpermanentLossProtection(
        string $protectionThreshold = '0.02',
        string $maxCoverage = '0.80',
        int $minHoldingPeriodHours = 168,
        string $fundSize = '0',
        array $metadata = []
    ): self {
        $this->recordThat(new ImpermanentLossProtectionEnabled(
            poolId: $this->poolId,
            protectionThreshold: $protectionThreshold,
            maxCoverage: $maxCoverage,
            minHoldingPeriodHours: $minHoldingPeriodHours,
            fundSize: $fundSize,
            metadata: $metadata
        ));

        return $this;
    }

    public function claimImpermanentLossProtection(
        string $providerId,
        string $positionId,
        string $impermanentLoss,
        string $impermanentLossPercent,
        string $compensation,
        string $compensationCurrency,
        array $metadata = []
    ): self {
        if (! $this->isActive) {
            throw new \DomainException('Cannot claim IL protection from inactive pool');
        }

        if (! isset($this->providers[$providerId])) {
            throw new \DomainException('Provider not found in pool');
        }

        if (BigDecimal::of($compensation)->isLessThanOrEqualTo(0)) {
            throw new \DomainException('Invalid compensation amount');
        }

        $this->recordThat(new ImpermanentLossProtectionClaimed(
            poolId: $this->poolId,
            providerId: $providerId,
            positionId: $positionId,
            impermanentLoss: $impermanentLoss,
            impermanentLossPercent: $impermanentLossPercent,
            compensation: $compensation,
            compensationCurrency: $compensationCurrency,
            metadata: $metadata
        ));

        return $this;
    }

    protected function applyImpermanentLossProtectionEnabled(ImpermanentLossProtectionEnabled $event): void
    {
        $this->metadata['il_protection_enabled'] = true;
        $this->metadata['il_protection_threshold'] = $event->protectionThreshold;
        $this->metadata['il_protection_max_coverage'] = $event->maxCoverage;
        $this->metadata['il_protection_min_holding_hours'] = $event->minHoldingPeriodHours;
        $this->metadata['il_protection_fund_size'] = $event->fundSize;
    }

    protected function applyImpermanentLossProtectionClaimed(ImpermanentLossProtectionClaimed $event): void
    {
        if (isset($this->providers[$event->providerId])) {
            $this->providers[$event->providerId]['il_claims'] =
                ($this->providers[$event->providerId]['il_claims'] ?? []);

            $this->providers[$event->providerId]['il_claims'][] = [
                'position_id'  => $event->positionId,
                'compensation' => $event->compensation,
                'claimed_at'   => now()->toIso8601String(),
            ];
        }
    }
}
