<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Workflows\Activities\BurnStablecoinActivity;
use App\Domain\Stablecoin\Workflows\Activities\ClosePositionActivity;
use App\Domain\Stablecoin\Workflows\Activities\LockCollateralActivity;
use App\Domain\Stablecoin\Workflows\Activities\MintStablecoinActivity;
use App\Domain\Stablecoin\Workflows\Activities\ReleaseCollateralActivity;
use App\Domain\Stablecoin\Workflows\Activities\UpdatePositionActivity;
use Generator;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;

class BurnStablecoinWorkflow extends Workflow
{
    /**
     * Execute stablecoin burning workflow with compensation pattern.
     */
    public function execute(
        AccountUuid $accountUuid,
        string $positionUuid,
        string $stablecoinCode,
        int $burnAmount,
        int $collateralReleaseAmount,
        bool $closePosition = false
    ): Generator {
        try {
            // Burn stablecoins from account
            yield ActivityStub::make(
                BurnStablecoinActivity::class,
                $accountUuid,
                $positionUuid,
                $stablecoinCode,
                $burnAmount
            );

            // Add compensation to mint stablecoins back on failure
            $this->addCompensation(
                fn () => ActivityStub::make(
                    MintStablecoinActivity::class,
                    $accountUuid,
                    $positionUuid,
                    $stablecoinCode,
                    $burnAmount
                )
            );

            // Release collateral to account
            yield ActivityStub::make(
                ReleaseCollateralActivity::class,
                $accountUuid,
                $positionUuid,
                null, // Asset code will be determined from position
                $collateralReleaseAmount
            );

            // Add compensation to lock collateral back on failure
            $this->addCompensation(
                fn () => ActivityStub::make(
                    LockCollateralActivity::class,
                    $accountUuid,
                    $positionUuid,
                    null, // Asset code will be determined from position
                    $collateralReleaseAmount
                )
            );

            if ($closePosition) {
                // Close the position if fully repaid
                yield ActivityStub::make(
                    ClosePositionActivity::class,
                    $positionUuid,
                    'debt_repaid'
                );
            } else {
                // Update position with new values
                yield ActivityStub::make(
                    UpdatePositionActivity::class,
                    $positionUuid
                );
            }

            return true;
        } catch (Throwable $th) {
            // Execute compensations in reverse order
            yield from $this->compensate();
            throw $th;
        }
    }
}
