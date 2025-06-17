<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Services;

use App\Domain\Account\DataObjects\Money;
use App\Domain\Asset\Services\ExchangeRateService;
use App\Models\Account;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StablecoinIssuanceService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService,
        private readonly CollateralService $collateralService
    ) {}

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
        if (!$stablecoin->canMint()) {
            throw new \RuntimeException("Minting is disabled for {$stablecoinCode}");
        }

        if ($stablecoin->hasReachedMaxSupply()) {
            throw new \RuntimeException("Maximum supply reached for {$stablecoinCode}");
        }

        // Validate collateral sufficiency
        $this->validateCollateralSufficiency($stablecoin, $collateralAssetCode, $collateralAmount, $mintAmount);

        // Check account has sufficient collateral
        if (!$account->hasSufficientBalance($collateralAssetCode, $collateralAmount)) {
            throw new \RuntimeException("Insufficient {$collateralAssetCode} balance for collateral");
        }

        return DB::transaction(function () use ($account, $stablecoin, $collateralAssetCode, $collateralAmount, $mintAmount) {
            // Lock collateral from account
            $account->subtractBalance($collateralAssetCode, $collateralAmount);

            // Find or create collateral position
            $position = StablecoinCollateralPosition::firstOrCreate([
                'account_uuid' => $account->uuid,
                'stablecoin_code' => $stablecoin->code,
            ], [
                'collateral_asset_code' => $collateralAssetCode,
                'collateral_amount' => 0,
                'debt_amount' => 0,
                'collateral_ratio' => 0,
                'status' => 'active',
                'last_interaction_at' => now(),
            ]);

            // Update position
            $position->collateral_amount += $collateralAmount;
            $position->debt_amount += $mintAmount;
            $position->last_interaction_at = now();
            $position->updateCollateralRatio();

            // Calculate and apply minting fee
            $fee = (int) ($mintAmount * $stablecoin->mint_fee);
            $netMintAmount = $mintAmount - $fee;

            // Add stablecoin to account balance
            $account->addBalance($stablecoin->code, $netMintAmount);

            // Update stablecoin global statistics
            $collateralValueInPegAsset = $this->collateralService->convertToPegAsset(
                $collateralAssetCode,
                $collateralAmount,
                $stablecoin->peg_asset_code
            );

            $stablecoin->increment('total_supply', $mintAmount);
            $stablecoin->increment('total_collateral_value', $collateralValueInPegAsset);

            Log::info('Stablecoin minted', [
                'account_uuid' => $account->uuid,
                'stablecoin_code' => $stablecoin->code,
                'collateral_asset' => $collateralAssetCode,
                'collateral_amount' => $collateralAmount,
                'mint_amount' => $mintAmount,
                'fee' => $fee,
                'net_amount' => $netMintAmount,
                'position_id' => $position->uuid,
            ]);

            return $position;
        });
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
        
        if (!$stablecoin->canBurn()) {
            throw new \RuntimeException("Burning is disabled for {$stablecoinCode}");
        }

        // Find the position
        $position = StablecoinCollateralPosition::where('account_uuid', $account->uuid)
            ->where('stablecoin_code', $stablecoinCode)
            ->where('status', 'active')
            ->firstOrFail();

        // Validate burn amount
        if ($burnAmount > $position->debt_amount) {
            throw new \RuntimeException("Cannot burn more than debt amount");
        }

        // Check account has sufficient stablecoin balance
        if (!$account->hasSufficientBalance($stablecoinCode, $burnAmount)) {
            throw new \RuntimeException("Insufficient {$stablecoinCode} balance to burn");
        }

        return DB::transaction(function () use ($account, $stablecoin, $position, $burnAmount, $collateralReleaseAmount) {
            // Calculate burn fee
            $fee = (int) ($burnAmount * $stablecoin->burn_fee);
            $totalBurnAmount = $burnAmount + $fee;

            // Burn stablecoins from account
            $account->subtractBalance($stablecoin->code, $totalBurnAmount);

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
                    throw new \RuntimeException("Collateral release would make position undercollateralized");
                }
            }

            // Update position
            $position->debt_amount = $newDebtAmount;
            $position->collateral_amount = $newCollateralAmount;
            $position->last_interaction_at = now();

            // Release collateral back to account
            $account->addBalance($position->collateral_asset_code, $collateralReleaseAmount);

            // Close position if fully repaid
            if ($position->debt_amount == 0) {
                $position->status = 'closed';
                // Release any remaining collateral
                if ($position->collateral_amount > 0) {
                    $account->addBalance($position->collateral_asset_code, $position->collateral_amount);
                    $position->collateral_amount = 0;
                }
            }

            $position->updateCollateralRatio();

            // Update stablecoin global statistics
            $collateralValueInPegAsset = $this->collateralService->convertToPegAsset(
                $position->collateral_asset_code,
                $collateralReleaseAmount,
                $stablecoin->peg_asset_code
            );

            $stablecoin->decrement('total_supply', $burnAmount);
            $stablecoin->decrement('total_collateral_value', $collateralValueInPegAsset);

            Log::info('Stablecoin burned', [
                'account_uuid' => $account->uuid,
                'stablecoin_code' => $stablecoin->code,
                'burn_amount' => $burnAmount,
                'fee' => $fee,
                'collateral_released' => $collateralReleaseAmount,
                'position_id' => $position->uuid,
                'position_status' => $position->status,
            ]);

            return $position;
        });
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
            throw new \RuntimeException("Collateral asset mismatch");
        }

        if (!$account->hasSufficientBalance($collateralAssetCode, $collateralAmount)) {
            throw new \RuntimeException("Insufficient {$collateralAssetCode} balance");
        }

        return DB::transaction(function () use ($account, $position, $collateralAmount) {
            // Transfer collateral from account
            $account->subtractBalance($position->collateral_asset_code, $collateralAmount);

            // Update position
            $position->collateral_amount += $collateralAmount;
            $position->last_interaction_at = now();
            $position->updateCollateralRatio();

            // Update global collateral value
            $stablecoin = $position->stablecoin;
            $collateralValueInPegAsset = $this->collateralService->convertToPegAsset(
                $position->collateral_asset_code,
                $collateralAmount,
                $stablecoin->peg_asset_code
            );
            $stablecoin->increment('total_collateral_value', $collateralValueInPegAsset);

            Log::info('Collateral added to position', [
                'account_uuid' => $account->uuid,
                'position_id' => $position->uuid,
                'collateral_amount' => $collateralAmount,
                'new_collateral_ratio' => $position->collateral_ratio,
            ]);

            return $position;
        });
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
            throw new \RuntimeException(
                "Insufficient collateral. Required ratio: {$stablecoin->collateral_ratio}, " .
                "provided ratio: {$ratio}"
            );
        }
    }
}