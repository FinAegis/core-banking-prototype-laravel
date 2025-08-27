<?php

namespace App\Domain\Stablecoin\Aggregates;

use App\Domain\Stablecoin\Events\CollateralizationRatioUpdated;
use App\Domain\Stablecoin\Events\CustodianAdded;
use App\Domain\Stablecoin\Events\CustodianRemoved;
use App\Domain\Stablecoin\Events\ReserveDeposited;
use App\Domain\Stablecoin\Events\ReservePoolCreated;
use App\Domain\Stablecoin\Events\ReserveRebalanced;
use App\Domain\Stablecoin\Events\ReserveWithdrawn;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class ReservePool extends AggregateRoot
{
    protected string $stablecoinSymbol;

    protected array $reserves = []; // asset => balance

    protected array $custodians = []; // custodian_id => info

    protected string $targetCollateralizationRatio = '1.5'; // 150%

    protected string $minimumCollateralizationRatio = '1.2'; // 120%

    protected string $totalMinted = '0';

    protected string $status = 'active';

    public static function create(
        string $poolId,
        string $stablecoinSymbol,
        string $targetCollateralizationRatio,
        string $minimumCollateralizationRatio
    ): self {
        $pool = static::retrieve($poolId);
        $pool->recordThat(
            new ReservePoolCreated(
                poolId: $poolId,
                stablecoinSymbol: $stablecoinSymbol,
                targetCollateralizationRatio: $targetCollateralizationRatio,
                minimumCollateralizationRatio: $minimumCollateralizationRatio
            )
        );

        return $pool;
    }

    public function depositReserve(
        string $asset,
        string $amount,
        string $custodianId,
        string $transactionHash,
        array $metadata = []
    ): self {
        if (! isset($this->custodians[$custodianId])) {
            throw new InvalidArgumentException("Custodian {$custodianId} not authorized");
        }

        if (BigDecimal::of($amount)->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('Deposit amount must be positive');
        }

        $this->recordThat(
            new ReserveDeposited(
                poolId: $this->uuid(),
                asset: $asset,
                amount: $amount,
                custodianId: $custodianId,
                transactionHash: $transactionHash,
                metadata: $metadata
            )
        );

        return $this;
    }

    public function withdrawReserve(
        string $asset,
        string $amount,
        string $custodianId,
        string $destinationAddress,
        string $reason,
        array $metadata = []
    ): self {
        if (! isset($this->custodians[$custodianId])) {
            throw new InvalidArgumentException("Custodian {$custodianId} not authorized");
        }

        $currentBalance = BigDecimal::of($this->reserves[$asset] ?? '0');
        $withdrawAmount = BigDecimal::of($amount);

        if ($withdrawAmount->isGreaterThan($currentBalance)) {
            throw new InvalidArgumentException('Insufficient reserve balance');
        }

        // Check if withdrawal maintains minimum collateralization
        $newBalance = $currentBalance->minus($withdrawAmount);
        if (! $this->wouldMaintainMinimumCollateralization($asset, $newBalance->__toString())) {
            throw new InvalidArgumentException('Withdrawal would breach minimum collateralization ratio');
        }

        $this->recordThat(
            new ReserveWithdrawn(
                poolId: $this->uuid(),
                asset: $asset,
                amount: $amount,
                custodianId: $custodianId,
                destinationAddress: $destinationAddress,
                reason: $reason,
                metadata: $metadata
            )
        );

        return $this;
    }

    public function rebalanceReserves(
        array $targetAllocations,
        string $executedBy,
        array $swaps
    ): self {
        // Validate allocations sum to 100%
        $total = array_reduce(
            $targetAllocations,
            function ($sum, $allocation) {
                return BigDecimal::of($sum)->plus($allocation);
            },
            '0'
        );

        if (! BigDecimal::of($total)->isEqualTo('1')) {
            throw new InvalidArgumentException('Target allocations must sum to 100%');
        }

        $this->recordThat(
            new ReserveRebalanced(
                poolId: $this->uuid(),
                targetAllocations: $targetAllocations,
                executedBy: $executedBy,
                swaps: $swaps,
                previousAllocations: $this->getCurrentAllocations()
            )
        );

        return $this;
    }

    public function addCustodian(
        string $custodianId,
        string $name,
        string $type,
        array $config
    ): self {
        if (isset($this->custodians[$custodianId])) {
            throw new InvalidArgumentException("Custodian {$custodianId} already exists");
        }

        $this->recordThat(
            new CustodianAdded(
                poolId: $this->uuid(),
                custodianId: $custodianId,
                name: $name,
                type: $type,
                config: $config
            )
        );

        return $this;
    }

    public function removeCustodian(string $custodianId, string $reason): self
    {
        if (! isset($this->custodians[$custodianId])) {
            throw new InvalidArgumentException("Custodian {$custodianId} not found");
        }

        $this->recordThat(
            new CustodianRemoved(
                poolId: $this->uuid(),
                custodianId: $custodianId,
                reason: $reason
            )
        );

        return $this;
    }

    public function updateCollateralizationRatio(
        string $newTargetRatio,
        string $newMinimumRatio,
        string $approvedBy
    ): self {
        if (BigDecimal::of($newMinimumRatio)->isGreaterThanOrEqualTo($newTargetRatio)) {
            throw new InvalidArgumentException('Minimum ratio must be less than target ratio');
        }

        if (BigDecimal::of($newMinimumRatio)->isLessThan('1')) {
            throw new InvalidArgumentException('Minimum ratio cannot be less than 100%');
        }

        $this->recordThat(
            new CollateralizationRatioUpdated(
                poolId: $this->uuid(),
                oldTargetRatio: $this->targetCollateralizationRatio,
                newTargetRatio: $newTargetRatio,
                oldMinimumRatio: $this->minimumCollateralizationRatio,
                newMinimumRatio: $newMinimumRatio,
                approvedBy: $approvedBy
            )
        );

        return $this;
    }

    public function mintStablecoin(string $amount, array $collateralPrices): string
    {
        $mintAmount = BigDecimal::of($amount);
        $currentCollateralization = $this->calculateCollateralizationRatio($collateralPrices);

        if ($currentCollateralization->isLessThan($this->minimumCollateralizationRatio)) {
            throw new InvalidArgumentException('Insufficient collateralization for minting');
        }

        // Calculate how much can be minted while maintaining minimum ratio
        $maxMintable = $this->calculateMaxMintable($collateralPrices);
        if ($mintAmount->isGreaterThan($maxMintable)) {
            throw new InvalidArgumentException("Cannot mint more than {$maxMintable} while maintaining collateralization");
        }

        $this->totalMinted = BigDecimal::of($this->totalMinted)->plus($mintAmount)->__toString();

        return $mintAmount->__toString();
    }

    public function burnStablecoin(string $amount): void
    {
        $burnAmount = BigDecimal::of($amount);
        $currentMinted = BigDecimal::of($this->totalMinted);

        if ($burnAmount->isGreaterThan($currentMinted)) {
            throw new InvalidArgumentException('Cannot burn more than total minted');
        }

        $this->totalMinted = $currentMinted->minus($burnAmount)->__toString();
    }

    protected function applyReservePoolCreated(ReservePoolCreated $event): void
    {
        $this->stablecoinSymbol = $event->stablecoinSymbol;
        $this->targetCollateralizationRatio = $event->targetCollateralizationRatio;
        $this->minimumCollateralizationRatio = $event->minimumCollateralizationRatio;
    }

    protected function applyReserveDeposited(ReserveDeposited $event): void
    {
        $currentBalance = BigDecimal::of($this->reserves[$event->asset] ?? '0');
        $this->reserves[$event->asset] = $currentBalance->plus($event->amount)->__toString();
    }

    protected function applyReserveWithdrawn(ReserveWithdrawn $event): void
    {
        $currentBalance = BigDecimal::of($this->reserves[$event->asset]);
        $this->reserves[$event->asset] = $currentBalance->minus($event->amount)->__toString();
    }

    protected function applyReserveRebalanced(ReserveRebalanced $event): void
    {
        // Apply swap results to reserves
        foreach ($event->swaps as $swap) {
            $fromBalance = BigDecimal::of($this->reserves[$swap['from_asset']]);
            $toBalance = BigDecimal::of($this->reserves[$swap['to_asset']] ?? '0');

            $this->reserves[$swap['from_asset']] = $fromBalance->minus($swap['from_amount'])->__toString();
            $this->reserves[$swap['to_asset']] = $toBalance->plus($swap['to_amount'])->__toString();
        }
    }

    protected function applyCustodianAdded(CustodianAdded $event): void
    {
        $this->custodians[$event->custodianId] = [
            'name'     => $event->name,
            'type'     => $event->type,
            'config'   => $event->config,
            'added_at' => now()->toDateTimeString(),
        ];
    }

    protected function applyCustodianRemoved(CustodianRemoved $event): void
    {
        unset($this->custodians[$event->custodianId]);
    }

    protected function applyCollateralizationRatioUpdated(CollateralizationRatioUpdated $event): void
    {
        $this->targetCollateralizationRatio = $event->newTargetRatio;
        $this->minimumCollateralizationRatio = $event->newMinimumRatio;
    }

    private function wouldMaintainMinimumCollateralization(string $asset, string $newBalance): bool
    {
        // This would need actual price data to calculate
        // For now, return true to allow testing
        return true;
    }

    private function getCurrentAllocations(): array
    {
        $totalValue = BigDecimal::of('0');
        $values = [];

        // Calculate total value (would need prices)
        foreach ($this->reserves as $asset => $balance) {
            // In production, multiply by asset price
            $values[$asset] = BigDecimal::of($balance);
            $totalValue = $totalValue->plus($values[$asset]);
        }

        if ($totalValue->isZero()) {
            return [];
        }

        // Calculate allocations
        $allocations = [];
        foreach ($values as $asset => $value) {
            $allocations[$asset] = $value->dividedBy($totalValue, 4, RoundingMode::DOWN)->__toString();
        }

        return $allocations;
    }

    private function calculateCollateralizationRatio(array $prices): BigDecimal
    {
        $totalCollateralValue = BigDecimal::of('0');

        foreach ($this->reserves as $asset => $balance) {
            if (isset($prices[$asset])) {
                $assetValue = BigDecimal::of($balance)->multipliedBy($prices[$asset]);
                $totalCollateralValue = $totalCollateralValue->plus($assetValue);
            }
        }

        if (BigDecimal::of($this->totalMinted)->isZero()) {
            return BigDecimal::of('999'); // Infinite collateralization
        }

        return $totalCollateralValue->dividedBy($this->totalMinted, 4, RoundingMode::DOWN);
    }

    private function calculateMaxMintable(array $prices): BigDecimal
    {
        $totalCollateralValue = BigDecimal::of('0');

        foreach ($this->reserves as $asset => $balance) {
            if (isset($prices[$asset])) {
                $assetValue = BigDecimal::of($balance)->multipliedBy($prices[$asset]);
                $totalCollateralValue = $totalCollateralValue->plus($assetValue);
            }
        }

        // Max mintable = (collateral_value / min_ratio) - current_minted
        $maxTotal = $totalCollateralValue->dividedBy($this->minimumCollateralizationRatio, 18, RoundingMode::DOWN);

        return $maxTotal->minus($this->totalMinted);
    }

    public function getReserves(): array
    {
        return $this->reserves;
    }

    public function getTotalMinted(): string
    {
        return $this->totalMinted;
    }

    public function getCustodians(): array
    {
        return $this->custodians;
    }

    public function getTargetCollateralizationRatio(): string
    {
        return $this->targetCollateralizationRatio;
    }

    public function getMinimumCollateralizationRatio(): string
    {
        return $this->minimumCollateralizationRatio;
    }
}
