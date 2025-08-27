<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Workflows\Activities\BurnStablecoinActivity;
use App\Domain\Stablecoin\Workflows\Activities\ClosePositionActivity;
use App\Domain\Stablecoin\Workflows\Activities\CreatePositionActivity;
use App\Domain\Stablecoin\Workflows\Activities\LockCollateralActivity;
use App\Domain\Stablecoin\Workflows\Activities\MintStablecoinActivity;
use App\Domain\Stablecoin\Workflows\Activities\ReleaseCollateralActivity;
use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

class MintStablecoinWorkflow extends Workflow
{
    /**
     * Execute stablecoin minting workflow with compensation pattern.
     */
    public function execute(
        AccountUuid $accountUuid,
        string $stablecoinCode,
        string $collateralAssetCode,
        int $collateralAmount,
        int $mintAmount,
        ?string $positionUuid = null
    ): Generator {
        try {
            // Create or update position
            $positionResult = yield ActivityStub::make(
                CreatePositionActivity::class,
                $accountUuid,
                $stablecoinCode,
                $collateralAssetCode,
                $collateralAmount,
                $mintAmount,
                $positionUuid
            );
            $positionUuid = $positionResult['position_uuid'];

            // Add compensation to close position on failure
            $this->addCompensation(
                fn () => ActivityStub::make(
                    ClosePositionActivity::class,
                    $positionUuid,
                    'failed'
                )
            );

            // Lock collateral from account
            yield ActivityStub::make(
                LockCollateralActivity::class,
                $accountUuid,
                $positionUuid,
                $collateralAssetCode,
                $collateralAmount
            );

            // Add compensation to release collateral on failure
            $this->addCompensation(
                fn () => ActivityStub::make(
                    ReleaseCollateralActivity::class,
                    $accountUuid,
                    $positionUuid,
                    $collateralAssetCode,
                    $collateralAmount
                )
            );

            // Mint stablecoins to account
            yield ActivityStub::make(
                MintStablecoinActivity::class,
                $accountUuid,
                $positionUuid,
                $stablecoinCode,
                $mintAmount
            );

            // Add compensation to burn stablecoins on failure
            $this->addCompensation(
                fn () => ActivityStub::make(
                    BurnStablecoinActivity::class,
                    $accountUuid,
                    $positionUuid,
                    $stablecoinCode,
                    $mintAmount
                )
            );

            return $positionUuid;
        } catch (Throwable $th) {
            // Execute compensations in reverse order
            yield from $this->compensate();
            throw $th;
        }
    }
}
