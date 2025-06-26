<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Aggregates\StablecoinAggregate;
use App\Domain\Wallet\Services\WalletService;
use App\Models\Stablecoin;
use Workflow\Activity;

class MintStablecoinActivity extends Activity
{
    /**
     * Mint stablecoins to account
     */
    public function execute(
        AccountUuid $accountUuid,
        string $positionUuid,
        string $stablecoinCode,
        int $amount
    ): bool {
        // Get stablecoin to calculate fees
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);
        
        // Calculate and apply minting fee
        $fee = (int) ($amount * $stablecoin->mint_fee);
        $netMintAmount = $amount - $fee;
        
        // Deposit stablecoins to account using wallet service
        $walletService = app(WalletService::class);
        $walletService->deposit($accountUuid, $stablecoinCode, $netMintAmount);
        
        // Record stablecoin minting in aggregate
        $aggregate = StablecoinAggregate::retrieve($positionUuid);
        $aggregate->mintStablecoin($amount);
        $aggregate->persist();
        
        // Update global stablecoin statistics
        $stablecoin->increment('total_supply', $amount);
        
        return true;
    }
}