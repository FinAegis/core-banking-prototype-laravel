<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Workflows\Activities\LockCollateralActivity;
use App\Domain\Stablecoin\Workflows\Activities\ReleaseCollateralActivity;
use App\Domain\Stablecoin\Workflows\Activities\UpdatePositionActivity;
use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

class AddCollateralWorkflow extends Workflow
{
    /**
     * Execute add collateral workflow with compensation pattern.
     */
    public function execute(
        AccountUuid $accountUuid,
        string $positionUuid,
        string $collateralAssetCode,
        int $collateralAmount
    ): Generator {
        try {
            // Lock additional collateral from account
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

            // Update position with new values
            yield ActivityStub::make(
                UpdatePositionActivity::class,
                $positionUuid
            );

            return true;
        } catch (Throwable $th) {
            // Execute compensations in reverse order
            yield from $this->compensate();
            throw $th;
        }
    }
}
