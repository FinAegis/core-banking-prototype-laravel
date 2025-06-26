<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Aggregates\StablecoinAggregate;
use App\Domain\Wallet\Services\WalletService;
use Workflow\Activity;

class LockCollateralActivity extends Activity
{
    /**
     * Lock collateral from account
     */
    public function execute(
        AccountUuid $accountUuid,
        string $positionUuid,
        string $collateralAssetCode,
        int $amount
    ): bool {
        // Withdraw collateral from account using wallet service
        $walletService = app(WalletService::class);
        $walletService->withdraw($accountUuid, $collateralAssetCode, $amount);
        
        // Record collateral lock in aggregate
        $aggregate = StablecoinAggregate::retrieve($positionUuid);
        $aggregate->lockCollateral($amount);
        $aggregate->persist();
        
        return true;
    }
}