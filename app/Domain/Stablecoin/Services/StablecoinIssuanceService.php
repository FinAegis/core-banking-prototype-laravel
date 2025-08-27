<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Stablecoin\Contracts\StablecoinIssuanceServiceInterface;
use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use App\Domain\Stablecoin\Workflows\AddCollateralWorkflow;
use App\Domain\Stablecoin\Workflows\BurnStablecoinWorkflow;
use App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Workflow\WorkflowStub;

class StablecoinIssuanceService implements StablecoinIssuanceServiceInterface
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly CollateralService $collateralService,
        private readonly WalletService $walletService
    ) {
    }

    /**
     * Mint stablecoins by locking collateral.
     */
    public function mint(
        Account $account,
        string $stablecoinCode,
        string $collateralAssetCode,
        int $collateralAmount,
        int $mintAmount
    ): StablecoinCollateralPosition {
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);

        // Validate stablecoin can be minted
        if (! $stablecoin->canMint()) {
            throw new RuntimeException("Minting is disabled for {$stablecoinCode}");
        }

        if ($stablecoin->hasReachedMaxSupply()) {
            throw new RuntimeException("Maximum supply reached for {$stablecoinCode}");
        }

        // Validate collateral sufficiency
        $this->validateCollateralSufficiency($stablecoin, $collateralAssetCode, $collateralAmount, $mintAmount);

        // Check account has sufficient collateral
        if (! $account->hasSufficientBalance($collateralAssetCode, $collateralAmount)) {
            throw new RuntimeException("Insufficient {$collateralAssetCode} balance for collateral");
        }

        // Find existing position if any
        $existingPosition = StablecoinCollateralPosition::where('account_uuid', $account->uuid)
            ->where('stablecoin_code', $stablecoin->code)
            ->where('status', 'active')
            ->first();

        // Execute minting workflow
        $workflow = WorkflowStub::make(MintStablecoinWorkflow::class);
        $positionUuid = $workflow->start(
            AccountUuid::fromString((string) $account->uuid),
            $stablecoin->code,
            $collateralAssetCode,
            $collateralAmount,
            $mintAmount,
            $existingPosition?->uuid
        )->await();

        // Update stablecoin global statistics
        $collateralValueInPegAsset = $this->collateralService->convertToPegAsset(
            $collateralAssetCode,
            $collateralAmount,
            $stablecoin->peg_asset_code
        );
        $stablecoin->increment('total_collateral_value', $collateralValueInPegAsset);

        Log::info(
            'Stablecoin minted',
            [
            'account_uuid'      => $account->uuid,
            'stablecoin_code'   => $stablecoin->code,
            'collateral_asset'  => $collateralAssetCode,
            'collateral_amount' => $collateralAmount,
            'mint_amount'       => $mintAmount,
            'position_id'       => $positionUuid,
            ]
        );

        // Return the updated position
        return StablecoinCollateralPosition::where('uuid', $positionUuid)->firstOrFail();
    }

    /**
     * Burn stablecoins and release collateral.
     */
    public function burn(
        Account $account,
        string $stablecoinCode,
        int $burnAmount,
        ?int $collateralReleaseAmount = null
    ): StablecoinCollateralPosition {
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);

        if (! $stablecoin->canBurn()) {
            throw new RuntimeException("Burning is disabled for {$stablecoinCode}");
        }

        // Find the position
        $position = StablecoinCollateralPosition::where('account_uuid', $account->uuid)
            ->where('stablecoin_code', $stablecoinCode)
            ->where('status', 'active')
            ->firstOrFail();

        // Validate burn amount
        if ($burnAmount > $position->debt_amount) {
            throw new RuntimeException('Cannot burn more than debt amount');
        }

        // Check account has sufficient stablecoin balance
        if (! $account->hasSufficientBalance($stablecoinCode, $burnAmount)) {
            throw new RuntimeException("Insufficient {$stablecoinCode} balance to burn");
        }

        // Calculate proportional collateral release if not specified
        if ($collateralReleaseAmount === null) {
            $releaseRatio = $burnAmount / $position->debt_amount;
            $collateralReleaseAmount = (int) ($position->collateral_amount * $releaseRatio);
        }

        // Validate collateral release doesn't make position unhealthy
        $newDebtAmount = $position->debt_amount - $burnAmount;
        $newCollateralAmount = $position->collateral_amount - $collateralReleaseAmount;

        if ($newDebtAmount > 0) {
            $newCollateralValue = $this->collateralService->convertToPegAsset(
                $position->collateral_asset_code,
                $newCollateralAmount,
                $stablecoin->peg_asset_code
            );
            $newRatio = $newCollateralValue / $newDebtAmount;

            if ($newRatio < $stablecoin->collateral_ratio) {
                throw new RuntimeException('Collateral release would make position undercollateralized');
            }
        }

        // Execute burning workflow
        $workflow = WorkflowStub::make(BurnStablecoinWorkflow::class);
        $workflow->start(
            AccountUuid::fromString((string) $account->uuid),
            $position->uuid,
            $stablecoin->code,
            $burnAmount,
            $collateralReleaseAmount,
            $newDebtAmount == 0 // closePosition flag
        )->await();

        // Update stablecoin global statistics
        $collateralValueInPegAsset = $this->collateralService->convertToPegAsset(
            $position->collateral_asset_code,
            $collateralReleaseAmount,
            $stablecoin->peg_asset_code
        );
        $stablecoin->decrement('total_collateral_value', $collateralValueInPegAsset);

        Log::info(
            'Stablecoin burned',
            [
            'account_uuid'        => $account->uuid,
            'stablecoin_code'     => $stablecoin->code,
            'burn_amount'         => $burnAmount,
            'collateral_released' => $collateralReleaseAmount,
            'position_id'         => $position->uuid,
            'position_status'     => $newDebtAmount == 0 ? 'closed' : 'active',
            ]
        );

        // Return the updated position
        return StablecoinCollateralPosition::where('uuid', $position->uuid)->firstOrFail();
    }

    /**
     * Add collateral to an existing position.
     */
    public function addCollateral(
        Account $account,
        string $stablecoinCode,
        string $collateralAssetCode,
        int $collateralAmount
    ): StablecoinCollateralPosition {
        $position = StablecoinCollateralPosition::where('account_uuid', $account->uuid)
            ->where('stablecoin_code', $stablecoinCode)
            ->where('status', 'active')
            ->firstOrFail();

        if ($position->collateral_asset_code !== $collateralAssetCode) {
            throw new RuntimeException('Collateral asset mismatch');
        }

        if (! $account->hasSufficientBalance($collateralAssetCode, $collateralAmount)) {
            throw new RuntimeException("Insufficient {$collateralAssetCode} balance");
        }

        // Execute add collateral workflow
        $workflow = WorkflowStub::make(AddCollateralWorkflow::class);
        $workflow->start(
            AccountUuid::fromString((string) $account->uuid),
            $position->uuid,
            $collateralAssetCode,
            $collateralAmount
        )->await();

        // Update global collateral value
        $stablecoin = $position->stablecoin;
        $collateralValueInPegAsset = $this->collateralService->convertToPegAsset(
            $position->collateral_asset_code,
            $collateralAmount,
            $stablecoin->peg_asset_code
        );
        $stablecoin->increment('total_collateral_value', $collateralValueInPegAsset);

        Log::info(
            'Collateral added to position',
            [
            'account_uuid'      => $account->uuid,
            'position_id'       => $position->uuid,
            'collateral_amount' => $collateralAmount,
            ]
        );

        // Return the updated position
        return StablecoinCollateralPosition::where('uuid', $position->uuid)->firstOrFail();
    }

    /**
     * Validate that collateral is sufficient for the mint amount.
     */
    private function validateCollateralSufficiency(
        Stablecoin $stablecoin,
        string $collateralAssetCode,
        int $collateralAmount,
        int $mintAmount
    ): void {
        // Convert collateral value to peg asset
        $collateralValueInPegAsset = $this->collateralService->convertToPegAsset(
            $collateralAssetCode,
            $collateralAmount,
            $stablecoin->peg_asset_code
        );

        // Calculate required collateral
        $requiredCollateral = $mintAmount * $stablecoin->collateral_ratio;

        if ($collateralValueInPegAsset < $requiredCollateral) {
            $ratio = $collateralValueInPegAsset / $mintAmount;
            throw new RuntimeException(
                "Insufficient collateral. Required ratio: {$stablecoin->collateral_ratio}, " .
                "provided ratio: {$ratio}"
            );
        }
    }
}
