<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Aggregates\StablecoinAggregate;
use App\Domain\Wallet\Services\WalletService;
use App\Models\StablecoinCollateralPosition;
use Workflow\Activity;

class ReleaseCollateralActivity extends Activity
{
    /**
     * Release collateral to account
     */
    public function execute(
        AccountUuid $accountUuid,
        string $positionUuid,
        ?string $collateralAssetCode,
        int $amount
    ): bool {
        // Get asset code from position if not provided
        if (!$collateralAssetCode) {
            $position = StablecoinCollateralPosition::where('uuid', $positionUuid)->firstOrFail();
            $collateralAssetCode = $position->collateral_asset_code;
        }
        
        // Deposit collateral to account using wallet service
        $walletService = app(WalletService::class);
        $walletService->deposit($accountUuid, $collateralAssetCode, $amount);
        
        // Record collateral release in aggregate
        $aggregate = StablecoinAggregate::retrieve($positionUuid);
        $aggregate->releaseCollateral($amount);
        $aggregate->persist();
        
        return true;
    }
}