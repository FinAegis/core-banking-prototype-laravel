<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Aggregates\StablecoinAggregate;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use Illuminate\Support\Str;
use Workflow\Activity;

class CreatePositionActivity extends Activity
{
    /**
     * Create or find a collateral position
     */
    public function execute(
        AccountUuid $accountUuid,
        string $stablecoinCode,
        string $collateralAssetCode,
        int $collateralAmount,
        int $mintAmount,
        ?string $positionUuid = null
    ): array {
        // Find existing position or create new UUID
        if (!$positionUuid) {
            $existingPosition = StablecoinCollateralPosition::where('account_uuid', $accountUuid->toString())
                ->where('stablecoin_code', $stablecoinCode)
                ->where('status', 'active')
                ->first();
            
            $positionUuid = $existingPosition ? $existingPosition->uuid : (string) Str::uuid();
        }
        
        // Get stablecoin for calculations
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);
        
        // Calculate collateral ratio
        $collateralService = app(CollateralService::class);
        $collateralValueInPegAsset = $collateralService->convertToPegAsset(
            $collateralAssetCode,
            $collateralAmount,
            $stablecoin->peg_asset_code
        );
        $collateralRatio = $collateralValueInPegAsset / $mintAmount;
        
        // Use event sourcing aggregate
        $aggregate = StablecoinAggregate::retrieve($positionUuid);
        
        if (!$existingPosition) {
            $aggregate->createPosition(
                $accountUuid->toString(),
                $stablecoinCode,
                $collateralAssetCode,
                0, // Initial amounts will be updated by subsequent events
                0,
                $collateralRatio
            );
        }
        
        $aggregate->persist();
        
        return [
            'position_uuid' => $positionUuid,
            'is_new' => !isset($existingPosition)
        ];
    }
}