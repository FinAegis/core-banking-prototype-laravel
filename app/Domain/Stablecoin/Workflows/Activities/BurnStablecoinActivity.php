<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Workflows\Activities;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Stablecoin\Aggregates\StablecoinAggregate;
use App\Domain\Wallet\Services\WalletService;
use App\Models\Stablecoin;
use Workflow\Activity;

class BurnStablecoinActivity extends Activity
{
    /**
     * Burn stablecoins from account
     */
    public function execute(
        AccountUuid $accountUuid,
        string $positionUuid,
        string $stablecoinCode,
        int $amount
    ): bool {
        // Get stablecoin to calculate fees
        $stablecoin = Stablecoin::findOrFail($stablecoinCode);
        
        // Calculate burn fee
        $fee = (int) ($amount * $stablecoin->burn_fee);
        $totalBurnAmount = $amount + $fee;
        
        // Withdraw stablecoins from account using wallet service
        $walletService = app(WalletService::class);
        $walletService->withdraw($accountUuid, $stablecoinCode, $totalBurnAmount);
        
        // Record stablecoin burning in aggregate
        $aggregate = StablecoinAggregate::retrieve($positionUuid);
        $aggregate->burnStablecoin($amount);
        $aggregate->persist();
        
        // Update global stablecoin statistics
        $stablecoin->decrement('total_supply', $amount);
        
        return true;
    }
}