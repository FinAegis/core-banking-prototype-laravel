<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Projectors;

use App\Domain\Stablecoin\Events\CollateralAdded;
use App\Domain\Stablecoin\Events\CollateralHealthChecked;
use App\Domain\Stablecoin\Events\CollateralLiquidationCompleted;
use App\Domain\Stablecoin\Events\CollateralLiquidationStarted;
use App\Domain\Stablecoin\Events\CollateralPositionClosed;
use App\Domain\Stablecoin\Events\CollateralPositionCreated;
use App\Domain\Stablecoin\Events\CollateralPriceUpdated;
use App\Domain\Stablecoin\Events\CollateralRebalanced;
use App\Domain\Stablecoin\Events\CollateralWithdrawn;
use App\Domain\Stablecoin\Events\MarginCallIssued;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class CollateralPositionProjector extends Projector
{
    public function onCollateralPositionCreated(CollateralPositionCreated $event): void
    {
        // Skip if this is our new enhanced collateral event structure
        // The existing StablecoinProjector handles the old structure
        if (! property_exists($event, 'positionId')) {
            return;
        }

        Log::info('CollateralPositionCreated event handled', [
            'position_id'  => $event->positionId,
            'owner_id'     => $event->ownerId,
            'collateral'   => $event->collateral,
            'initial_debt' => $event->initialDebt,
        ]);

        // For now, we just log the event
        // In production, this would update a read model
    }

    public function onCollateralAdded(CollateralAdded $event): void
    {
        Log::info('CollateralAdded event handled', [
            'position_id' => $event->positionId,
            'collateral'  => $event->collateral,
        ]);
    }

    public function onCollateralWithdrawn(CollateralWithdrawn $event): void
    {
        Log::info('CollateralWithdrawn event handled', [
            'position_id'          => $event->positionId,
            'remaining_collateral' => $event->remainingCollateral,
        ]);
    }

    public function onCollateralPriceUpdated(CollateralPriceUpdated $event): void
    {
        Log::info('CollateralPriceUpdated event handled', [
            'position_id' => $event->positionId,
            'asset'       => $event->asset,
            'new_price'   => $event->newPrice,
        ]);
    }

    public function onCollateralHealthChecked(CollateralHealthChecked $event): void
    {
        Log::info('CollateralHealthChecked event handled', [
            'position_id'  => $event->positionId,
            'health_ratio' => $event->healthRatio,
            'status'       => $event->status,
        ]);
    }

    public function onMarginCallIssued(MarginCallIssued $event): void
    {
        Log::info('MarginCallIssued event handled', [
            'position_id'    => $event->positionId,
            'current_ratio'  => $event->currentRatio,
            'required_ratio' => $event->requiredRatio,
        ]);
    }

    public function onCollateralLiquidationStarted(CollateralLiquidationStarted $event): void
    {
        Log::info('CollateralLiquidationStarted event handled', [
            'position_id'      => $event->positionId,
            'collateral_value' => $event->collateralValue,
            'debt_amount'      => $event->debtAmount,
        ]);
    }

    public function onCollateralLiquidationCompleted(CollateralLiquidationCompleted $event): void
    {
        Log::info('CollateralLiquidationCompleted event handled', [
            'position_id'    => $event->positionId,
            'auction_result' => $event->auctionResult,
        ]);
    }

    public function onCollateralRebalanced(CollateralRebalanced $event): void
    {
        Log::info('CollateralRebalanced event handled', [
            'position_id'    => $event->positionId,
            'new_allocation' => $event->newAllocation,
        ]);
    }

    public function onCollateralPositionClosed(CollateralPositionClosed $event): void
    {
        Log::info('CollateralPositionClosed event handled', [
            'position_id'          => $event->positionId,
            'remaining_collateral' => $event->remainingCollateral,
            'final_debt'           => $event->finalDebt,
        ]);
    }
}
