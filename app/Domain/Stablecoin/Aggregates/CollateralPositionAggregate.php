<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Aggregates;

use App\Domain\Shared\ValueObjects\Hash;
use App\Domain\Stablecoin\Events\CollateralAdded;
use App\Domain\Stablecoin\Events\CollateralHealthChecked;
use App\Domain\Stablecoin\Events\CollateralLiquidationCompleted;
use App\Domain\Stablecoin\Events\CollateralLiquidationStarted;
use App\Domain\Stablecoin\Events\CollateralPriceUpdated;
use App\Domain\Stablecoin\Events\CollateralRebalanced;
use App\Domain\Stablecoin\Events\CollateralWithdrawn;
use App\Domain\Stablecoin\Events\EnhancedCollateralPositionClosed;
use App\Domain\Stablecoin\Events\EnhancedCollateralPositionCreated;
use App\Domain\Stablecoin\Events\MarginCallIssued;
use App\Domain\Stablecoin\Repositories\CollateralPositionEventRepository;
use App\Domain\Stablecoin\Repositories\CollateralPositionSnapshotRepository;
use App\Domain\Stablecoin\ValueObjects\CollateralRatio;
use App\Domain\Stablecoin\ValueObjects\CollateralType;
use App\Domain\Stablecoin\ValueObjects\LiquidationThreshold;
use App\Domain\Stablecoin\ValueObjects\PositionHealth;
use Brick\Math\BigDecimal;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class CollateralPositionAggregate extends AggregateRoot
{
    protected string $positionId;

    protected string $ownerId;

    protected array $collateral = [];

    protected BigDecimal $totalDebt;

    protected ?BigDecimal $currentPrice = null;

    protected ?CollateralRatio $collateralRatio = null;

    protected LiquidationThreshold $liquidationThreshold;

    protected ?PositionHealth $health = null;

    protected bool $isActive = true;

    protected bool $isUnderMarginCall = false;

    protected bool $isBeingLiquidated = false;

    protected array $priceHistory = [];

    public function createPosition(
        string $positionId,
        string $ownerId,
        array $initialCollateral,
        BigDecimal $initialDebt,
        CollateralType $collateralType,
        LiquidationThreshold $liquidationThreshold
    ): self {
        $this->recordThat(new EnhancedCollateralPositionCreated(
            positionId: $positionId,
            ownerId: $ownerId,
            collateral: $initialCollateral,
            initialDebt: $initialDebt->toFloat(),
            collateralType: $collateralType->value,
            liquidationThreshold: $liquidationThreshold->liquidationPercentage(),
            hash: Hash::fromData([
                $positionId,
                $ownerId,
                $initialCollateral,
                $initialDebt->toFloat(),
                $collateralType->value,
            ]),
            createdAt: new \DateTimeImmutable()
        ));

        return $this;
    }

    public function addCollateral(array $additionalCollateral): self
    {
        if (! $this->isActive) {
            throw new \DomainException('Cannot add collateral to inactive position');
        }

        if ($this->isBeingLiquidated) {
            throw new \DomainException('Cannot add collateral during liquidation');
        }

        $this->recordThat(new CollateralAdded(
            positionId: $this->positionId,
            collateral: $additionalCollateral,
            newTotalCollateral: $this->calculateNewTotalCollateral($additionalCollateral),
            hash: Hash::fromData([
                $this->positionId,
                $additionalCollateral,
                time(),
            ]),
            addedAt: new \DateTimeImmutable()
        ));

        return $this;
    }

    public function withdrawCollateral(array $withdrawalAmount): self
    {
        if (! $this->isActive) {
            throw new \DomainException('Cannot withdraw from inactive position');
        }

        if ($this->isBeingLiquidated) {
            throw new \DomainException('Cannot withdraw during liquidation');
        }

        if ($this->isUnderMarginCall) {
            throw new \DomainException('Cannot withdraw while under margin call');
        }

        // Check if withdrawal would make position unhealthy
        $remainingCollateral = $this->calculateRemainingCollateral($withdrawalAmount);
        if (! $this->wouldBeHealthyAfterWithdrawal($remainingCollateral)) {
            throw new \DomainException('Withdrawal would make position unhealthy');
        }

        $this->recordThat(new CollateralWithdrawn(
            positionId: $this->positionId,
            withdrawn: $withdrawalAmount,
            remainingCollateral: $remainingCollateral,
            hash: Hash::fromData([
                $this->positionId,
                $withdrawalAmount,
                time(),
            ]),
            withdrawnAt: new \DateTimeImmutable()
        ));

        return $this;
    }

    public function updatePrice(BigDecimal $newPrice): self
    {
        $previousPrice = $this->currentPrice ?? BigDecimal::zero();
        $priceChange = $previousPrice->isZero()
            ? BigDecimal::zero()
            : $newPrice->minus($previousPrice)->dividedBy($previousPrice, 4);

        $this->recordThat(new CollateralPriceUpdated(
            positionId: $this->positionId,
            oldPrice: $previousPrice->toFloat(),
            newPrice: $newPrice->toFloat(),
            priceChange: $priceChange->toFloat(),
            hash: Hash::fromData([
                $this->positionId,
                $newPrice->toFloat(),
                time(),
            ]),
            updatedAt: new \DateTimeImmutable()
        ));

        // Check health after price update
        $this->checkHealth();

        return $this;
    }

    public function checkHealth(): self
    {
        $health = $this->calculateHealth();

        $this->recordThat(new CollateralHealthChecked(
            positionId: $this->positionId,
            healthRatio: $health->ratio()->toFloat(),
            isHealthy: $health->isHealthy(),
            requiresAction: $health->requiresAction(),
            hash: Hash::fromData([
                $this->positionId,
                $health->ratio()->toFloat(),
                time(),
            ]),
            checkedAt: new \DateTimeImmutable()
        ));

        // Issue margin call if needed
        if ($health->requiresMarginCall() && ! $this->isUnderMarginCall) {
            $this->issueMarginCall();
        }

        // Start liquidation if critical
        if ($health->requiresLiquidation() && ! $this->isBeingLiquidated) {
            $this->startLiquidation();
        }

        return $this;
    }

    public function issueMarginCall(): self
    {
        // Calculate current ratio if not set
        $currentRatio = $this->collateralRatio
            ? $this->collateralRatio->value()->toFloat()
            : $this->calculateHealth()->ratio()->toFloat();

        $this->recordThat(new MarginCallIssued(
            positionId: $this->positionId,
            ownerId: $this->ownerId,
            currentRatio: $currentRatio,
            requiredRatio: $this->liquidationThreshold->marginCallLevel()->toFloat(),
            timeToRespond: 24, // hours
            hash: Hash::fromData([
                $this->positionId,
                $this->ownerId,
                time(),
            ]),
            issuedAt: new \DateTimeImmutable()
        ));

        return $this;
    }

    public function startLiquidation(): self
    {
        if (! $this->isActive) {
            throw new \DomainException('Cannot liquidate inactive position');
        }

        $this->recordThat(new CollateralLiquidationStarted(
            positionId: $this->positionId,
            ownerId: $this->ownerId,
            collateralValue: $this->calculateCollateralValue()->toFloat(),
            debtAmount: $this->totalDebt->toFloat(),
            liquidationReason: 'Below liquidation threshold',
            hash: Hash::fromData([
                $this->positionId,
                $this->totalDebt->toFloat(),
                time(),
            ]),
            startedAt: new \DateTimeImmutable()
        ));

        return $this;
    }

    public function completeLiquidation(
        BigDecimal $liquidatedAmount,
        BigDecimal $remainingDebt,
        array $liquidationDetails
    ): self {
        $this->recordThat(new CollateralLiquidationCompleted(
            positionId: $this->positionId,
            liquidatedAmount: $liquidatedAmount->toFloat(),
            remainingDebt: $remainingDebt->toFloat(),
            liquidationDetails: $liquidationDetails,
            hash: Hash::fromData([
                $this->positionId,
                $liquidatedAmount->toFloat(),
                $remainingDebt->toFloat(),
                time(),
            ]),
            completedAt: new \DateTimeImmutable()
        ));

        if ($remainingDebt->isZero()) {
            $this->closePosition('Liquidation completed');
        }

        return $this;
    }

    public function rebalanceCollateral(array $newAllocation): self
    {
        if (! $this->isActive) {
            throw new \DomainException('Cannot rebalance inactive position');
        }

        if ($this->isBeingLiquidated) {
            throw new \DomainException('Cannot rebalance during liquidation');
        }

        $this->recordThat(new CollateralRebalanced(
            positionId: $this->positionId,
            oldAllocation: $this->collateral,
            newAllocation: $newAllocation,
            rebalanceReason: 'Portfolio optimization',
            hash: Hash::fromData([
                $this->positionId,
                $newAllocation,
                time(),
            ]),
            rebalancedAt: new \DateTimeImmutable()
        ));

        return $this;
    }

    public function closePosition(string $reason): self
    {
        if (! $this->isActive) {
            throw new \DomainException('Position already closed');
        }

        $this->recordThat(new EnhancedCollateralPositionClosed(
            positionId: $this->positionId,
            ownerId: $this->ownerId,
            finalCollateral: $this->collateral,
            finalDebt: $this->totalDebt->toFloat(),
            closureReason: $reason,
            hash: Hash::fromData([
                $this->positionId,
                $reason,
                time(),
            ]),
            closedAt: new \DateTimeImmutable()
        ));

        return $this;
    }

    // Apply event methods
    protected function applyEnhancedCollateralPositionCreated(EnhancedCollateralPositionCreated $event): void
    {
        $this->positionId = $event->positionId;
        $this->ownerId = $event->ownerId;
        $this->collateral = $event->collateral;
        $this->totalDebt = BigDecimal::of($event->initialDebt);
        $this->liquidationThreshold = new LiquidationThreshold($event->liquidationThreshold);
        $this->isActive = true;
    }

    protected function applyCollateralAdded(CollateralAdded $event): void
    {
        foreach ($event->collateral as $asset => $amount) {
            $this->collateral[$asset] = ($this->collateral[$asset] ?? 0) + $amount;
        }
    }

    protected function applyCollateralWithdrawn(CollateralWithdrawn $event): void
    {
        $this->collateral = $event->remainingCollateral;
    }

    protected function applyCollateralPriceUpdated(CollateralPriceUpdated $event): void
    {
        $this->currentPrice = BigDecimal::of($event->newPrice);
        $this->priceHistory[] = [
            'price'     => $event->newPrice,
            'timestamp' => $event->updatedAt,
        ];
    }

    protected function applyCollateralHealthChecked(CollateralHealthChecked $event): void
    {
        $this->health = new PositionHealth(
            BigDecimal::of($event->healthRatio),
            $event->isHealthy,
            $event->requiresAction
        );
    }

    protected function applyMarginCallIssued(MarginCallIssued $event): void
    {
        $this->isUnderMarginCall = true;
    }

    protected function applyCollateralLiquidationStarted(CollateralLiquidationStarted $event): void
    {
        $this->isBeingLiquidated = true;
    }

    protected function applyCollateralLiquidationCompleted(CollateralLiquidationCompleted $event): void
    {
        $this->isBeingLiquidated = false;
        $this->totalDebt = BigDecimal::of($event->remainingDebt);
    }

    protected function applyCollateralRebalanced(CollateralRebalanced $event): void
    {
        $this->collateral = $event->newAllocation;
    }

    protected function applyEnhancedCollateralPositionClosed(EnhancedCollateralPositionClosed $event): void
    {
        $this->isActive = false;
    }

    // Helper methods
    private function calculateNewTotalCollateral(array $additional): array
    {
        $total = $this->collateral;
        foreach ($additional as $asset => $amount) {
            $total[$asset] = ($total[$asset] ?? 0) + $amount;
        }

        return $total;
    }

    private function calculateRemainingCollateral(array $withdrawal): array
    {
        $remaining = $this->collateral;
        foreach ($withdrawal as $asset => $amount) {
            if (! isset($remaining[$asset]) || $remaining[$asset] < $amount) {
                throw new \DomainException("Insufficient collateral for asset: $asset");
            }
            $remaining[$asset] -= $amount;
        }

        return $remaining;
    }

    private function wouldBeHealthyAfterWithdrawal(array $remainingCollateral): bool
    {
        $value = $this->calculateCollateralValueForAssets($remainingCollateral);
        $ratio = $value->dividedBy($this->totalDebt, 4);

        return $ratio->isGreaterThan($this->liquidationThreshold->safeLevel());
    }

    private function calculateCollateralValue(): BigDecimal
    {
        return $this->calculateCollateralValueForAssets($this->collateral);
    }

    private function calculateCollateralValueForAssets(array $assets): BigDecimal
    {
        $totalValue = BigDecimal::zero();
        foreach ($assets as $asset => $amount) {
            // In production, this would fetch real prices
            $assetPrice = $this->getAssetPrice($asset);
            $totalValue = $totalValue->plus($assetPrice->multipliedBy($amount));
        }

        return $totalValue;
    }

    private function getAssetPrice(string $asset): BigDecimal
    {
        // Simplified - would use oracle service in production
        return match ($asset) {
            'ETH'   => BigDecimal::of('2000'),
            'BTC'   => BigDecimal::of('40000'),
            'USD'   => BigDecimal::of('1'),
            default => BigDecimal::of('1'),
        };
    }

    private function calculateHealth(): PositionHealth
    {
        $collateralValue = $this->calculateCollateralValue();

        // If there's no debt, the position is infinitely healthy
        if ($this->totalDebt->isZero()) {
            return new PositionHealth(
                BigDecimal::of('999'),  // Very high ratio
                true,  // is healthy
                false  // no margin call needed
            );
        }

        $ratio = $collateralValue->dividedBy($this->totalDebt, 4, \Brick\Math\RoundingMode::DOWN);

        return new PositionHealth(
            $ratio,
            $ratio->isGreaterThan($this->liquidationThreshold->safeLevel()),
            $ratio->isLessThan($this->liquidationThreshold->marginCallLevel())
        );
    }

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app()->make(CollateralPositionEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app()->make(CollateralPositionSnapshotRepository::class);
    }

    public function getState(): array
    {
        return [
            'positionId'           => $this->positionId,
            'ownerId'              => $this->ownerId,
            'collateral'           => $this->collateral,
            'totalDebt'            => $this->totalDebt->toFloat(),
            'currentPrice'         => $this->currentPrice?->toFloat(),
            'collateralRatio'      => $this->collateralRatio?->value()->toFloat(),
            'liquidationThreshold' => [
                'value'       => $this->liquidationThreshold->value(),
                'liquidation' => $this->liquidationThreshold->liquidationPercentage(),
                'marginCall'  => $this->liquidationThreshold->marginCallPercentage(),
                'safe'        => $this->liquidationThreshold->safePercentage(),
            ],
            'health' => $this->health ? [
                'ratio'          => $this->health->ratioPercentage(),
                'isHealthy'      => $this->health->isHealthy(),
                'requiresAction' => $this->health->requiresAction(),
                'status'         => $this->health->status(),
            ] : null,
            'isActive'          => $this->isActive,
            'isUnderMarginCall' => $this->isUnderMarginCall,
            'isBeingLiquidated' => $this->isBeingLiquidated,
            'priceHistory'      => $this->priceHistory,
        ];
    }
}
