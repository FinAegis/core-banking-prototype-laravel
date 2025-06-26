<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Aggregates;

use App\Domain\Stablecoin\Events\CollateralLocked;
use App\Domain\Stablecoin\Events\CollateralPositionClosed;
use App\Domain\Stablecoin\Events\CollateralPositionCreated;
use App\Domain\Stablecoin\Events\CollateralPositionLiquidated;
use App\Domain\Stablecoin\Events\CollateralPositionUpdated;
use App\Domain\Stablecoin\Events\CollateralReleased;
use App\Domain\Stablecoin\Events\StablecoinBurned;
use App\Domain\Stablecoin\Events\StablecoinMinted;
use App\Domain\Stablecoin\Repositories\StablecoinEventRepository;
use App\Domain\Stablecoin\Repositories\StablecoinSnapshotRepository;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class StablecoinAggregate extends AggregateRoot
{
    private string $position_uuid;
    private string $account_uuid;
    private string $stablecoin_code;
    private string $collateral_asset_code;
    private int $collateral_amount = 0;
    private int $debt_amount = 0;
    private string $status = 'active';

    /**
     * @return StablecoinEventRepository
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getStoredEventRepository(): StablecoinEventRepository
    {
        return app()->make(
            abstract: StablecoinEventRepository::class
        );
    }

    /**
     * @return StablecoinSnapshotRepository
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    protected function getSnapshotRepository(): StablecoinSnapshotRepository
    {
        return app()->make(
            abstract: StablecoinSnapshotRepository::class
        );
    }

    public function createPosition(
        string $account_uuid,
        string $stablecoin_code,
        string $collateral_asset_code,
        int $collateral_amount,
        int $debt_amount,
        float $collateral_ratio
    ): self {
        $this->recordThat(new CollateralPositionCreated(
            position_uuid: $this->uuid(),
            account_uuid: $account_uuid,
            stablecoin_code: $stablecoin_code,
            collateral_asset_code: $collateral_asset_code,
            collateral_amount: $collateral_amount,
            debt_amount: $debt_amount,
            collateral_ratio: $collateral_ratio,
            status: 'active'
        ));

        return $this;
    }

    public function lockCollateral(int $amount): self
    {
        $this->recordThat(new CollateralLocked(
            position_uuid: $this->uuid(),
            account_uuid: $this->account_uuid,
            collateral_asset_code: $this->collateral_asset_code,
            amount: $amount
        ));

        return $this;
    }

    public function mintStablecoin(int $amount): self
    {
        $this->recordThat(new StablecoinMinted(
            position_uuid: $this->uuid(),
            account_uuid: $this->account_uuid,
            stablecoin_code: $this->stablecoin_code,
            amount: $amount
        ));

        return $this;
    }

    public function burnStablecoin(int $amount): self
    {
        $this->recordThat(new StablecoinBurned(
            position_uuid: $this->uuid(),
            account_uuid: $this->account_uuid,
            stablecoin_code: $this->stablecoin_code,
            amount: $amount
        ));

        return $this;
    }

    public function releaseCollateral(int $amount): self
    {
        $this->recordThat(new CollateralReleased(
            position_uuid: $this->uuid(),
            account_uuid: $this->account_uuid,
            collateral_asset_code: $this->collateral_asset_code,
            amount: $amount
        ));

        return $this;
    }

    public function updatePosition(int $collateral_amount, int $debt_amount, float $collateral_ratio): self
    {
        $this->recordThat(new CollateralPositionUpdated(
            position_uuid: $this->uuid(),
            collateral_amount: $collateral_amount,
            debt_amount: $debt_amount,
            collateral_ratio: $collateral_ratio,
            status: $this->status
        ));

        return $this;
    }

    public function closePosition(string $reason = 'user_closed'): self
    {
        $this->recordThat(new CollateralPositionClosed(
            position_uuid: $this->uuid(),
            reason: $reason
        ));

        return $this;
    }

    public function liquidatePosition(
        string $liquidator_account_uuid,
        int $collateral_seized,
        int $debt_repaid,
        int $liquidation_penalty
    ): self {
        $this->recordThat(new CollateralPositionLiquidated(
            position_uuid: $this->uuid(),
            liquidator_account_uuid: $liquidator_account_uuid,
            collateral_seized: $collateral_seized,
            debt_repaid: $debt_repaid,
            liquidation_penalty: $liquidation_penalty
        ));

        return $this;
    }

    // Event handlers
    protected function applyCollateralPositionCreated(CollateralPositionCreated $event): void
    {
        $this->position_uuid = $event->position_uuid;
        $this->account_uuid = $event->account_uuid;
        $this->stablecoin_code = $event->stablecoin_code;
        $this->collateral_asset_code = $event->collateral_asset_code;
        $this->collateral_amount = $event->collateral_amount;
        $this->debt_amount = $event->debt_amount;
        $this->status = $event->status;
    }

    protected function applyCollateralLocked(CollateralLocked $event): void
    {
        $this->collateral_amount += $event->amount;
    }

    protected function applyCollateralReleased(CollateralReleased $event): void
    {
        $this->collateral_amount -= $event->amount;
    }

    protected function applyStablecoinMinted(StablecoinMinted $event): void
    {
        $this->debt_amount += $event->amount;
    }

    protected function applyStablecoinBurned(StablecoinBurned $event): void
    {
        $this->debt_amount -= $event->amount;
    }

    protected function applyCollateralPositionUpdated(CollateralPositionUpdated $event): void
    {
        $this->collateral_amount = $event->collateral_amount;
        $this->debt_amount = $event->debt_amount;
        $this->status = $event->status;
    }

    protected function applyCollateralPositionClosed(CollateralPositionClosed $event): void
    {
        $this->status = 'closed';
    }

    protected function applyCollateralPositionLiquidated(CollateralPositionLiquidated $event): void
    {
        $this->status = 'liquidated';
        $this->collateral_amount = 0;
        $this->debt_amount = 0;
    }
}