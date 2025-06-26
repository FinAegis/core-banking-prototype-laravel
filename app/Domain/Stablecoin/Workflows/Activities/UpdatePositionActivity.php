<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Stablecoin\Aggregates\StablecoinAggregate;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Models\StablecoinCollateralPosition;
use Workflow\Activity;

class UpdatePositionActivity extends Activity
{
    /**
     * Update position with recalculated values
     */
    public function execute(
        string $positionUuid
    ): bool {
        // Get current position
        $position = StablecoinCollateralPosition::where('uuid', $positionUuid)->firstOrFail();
        
        // Calculate new collateral ratio
        $collateralService = app(CollateralService::class);
        $collateralValueInPegAsset = $collateralService->convertToPegAsset(
            $position->collateral_asset_code,
            $position->collateral_amount,
            $position->stablecoin->peg_asset_code
        );
        
        $newRatio = $position->debt_amount > 0 
            ? $collateralValueInPegAsset / $position->debt_amount 
            : 0;
        
        // Update position in aggregate
        $aggregate = StablecoinAggregate::retrieve($positionUuid);
        $aggregate->updatePosition(
            $position->collateral_amount,
            $position->debt_amount,
            $newRatio
        );
        $aggregate->persist();
        
        return true;
    }
}