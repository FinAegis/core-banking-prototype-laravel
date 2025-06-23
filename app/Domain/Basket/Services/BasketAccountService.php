<?php

declare(strict_types=1);

namespace App\Domain\Basket\Services;

use App\Models\Account;
use App\Models\BasketAsset;
use App\Models\AccountBalance;
use App\Domain\Basket\Events\BasketDecomposed;
use App\Domain\Account\Events\AssetBalanceAdded;
use App\Domain\Account\Events\AssetBalanceSubtracted;
use App\Domain\Account\DataObjects\Hash;
use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Wallet\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BasketAccountService
{
    public function __construct(
        private readonly BasketValueCalculationService $valueCalculationService,
        private readonly WalletService $walletService
    ) {}

    /**
     * Add basket asset balance to an account.
     */
    public function addBasketBalance(Account $account, string $basketCode, int $amount): AccountBalance
    {
        $basket = BasketAsset::where('code', $basketCode)->first();
        
        if (!$basket) {
            throw new \Exception("Basket not found: {$basketCode}");
        }
        
        if (!$basket->is_active) {
            throw new \Exception("Basket {$basketCode} is not active");
        }

        // Ensure the basket exists as an asset
        $basket->toAsset();

        // Add balance like any other asset
        $balance = $account->balances()->firstOrCreate(
            ['asset_code' => $basketCode],
            ['balance' => 0]
        );

        $balance->credit($amount);

        // Record event
        event(new AssetBalanceAdded(
            assetCode: $basketCode,
            amount: $amount,
            hash: new Hash(hash('sha3-512', "basket_deposit:{$account->uuid}:{$basketCode}:{$amount}:" . now()->timestamp)),
            metadata: ['type' => 'basket_deposit', 'account_uuid' => (string) $account->uuid]
        ));

        Log::info("Added {$amount} of basket {$basketCode} to account {$account->uuid}");

        return $balance;
    }

    /**
     * Subtract basket asset balance from an account.
     */
    public function subtractBasketBalance(Account $account, string $basketCode, int $amount): AccountBalance
    {
        $balance = $account->balances()
            ->where('asset_code', $basketCode)
            ->firstOrFail();

        if ($balance->balance < $amount) {
            throw new \Exception("Insufficient basket balance. Available: {$balance->balance}, Requested: {$amount}");
        }

        $balance->debit($amount);

        // Record event
        event(new AssetBalanceSubtracted(
            assetCode: $basketCode,
            amount: $amount,
            hash: new Hash(hash('sha3-512', "basket_withdraw:{$account->uuid}:{$basketCode}:{$amount}:" . now()->timestamp)),
            metadata: ['type' => 'basket_withdrawal', 'account_uuid' => (string) $account->uuid]
        ));

        Log::info("Subtracted {$amount} of basket {$basketCode} from account {$account->uuid}");

        return $balance;
    }

    /**
     * Decompose a basket into its component assets.
     * This converts basket holdings into individual asset holdings.
     */
    public function decomposeBasket(Account $account, string $basketCode, int $amount): array
    {
        $basket = BasketAsset::where('code', $basketCode)->first();
        
        if (!$basket) {
            throw new \Exception("Basket not found: {$basketCode}");
        }
        
        if (!$basket->is_active) {
            throw new \Exception("Basket {$basketCode} is not active");
        }

        // Validate positive amount
        if ($amount <= 0) {
            throw new \Exception("Amount must be positive");
        }

        // Note: We don't validate weights here since this is an operational function
        // and users should be able to decompose even if basket weights are temporarily invalid

        // Check sufficient balance
        $basketBalance = $account->balances()
            ->where('asset_code', $basketCode)
            ->first();

        if (!$basketBalance || $basketBalance->balance < $amount) {
            $availableBalance = $basketBalance ? $basketBalance->balance : 0;
            throw new \Exception("Insufficient basket balance for decomposition. Required: {$amount}, Available: {$availableBalance}");
        }

        return DB::transaction(function () use ($account, $basket, $basketCode, $amount, $basketBalance) {
            // Calculate component amounts based on weights
            $componentAmounts = $this->calculateComponentAmounts($basket, $amount);

            // Use WalletService for proper Service → Workflow → Activity → Aggregate architecture
            $accountUuid = AccountUuid::fromString($account->uuid);
            
            // Subtract basket balance using WalletService
            $this->walletService->withdraw($accountUuid, $basketCode, $amount);

            // Add component balances using WalletService
            foreach ($componentAmounts as $assetCode => $componentAmount) {
                $this->walletService->deposit($accountUuid, $assetCode, $componentAmount);
            }

            // Record decomposition event
            event(new BasketDecomposed(
                accountUuid: (string) $account->uuid,
                basketCode: $basketCode,
                amount: $amount,
                componentAmounts: $componentAmounts,
                decomposedAt: now()
            ));

            Log::info("Decomposed {$amount} of basket {$basketCode} for account {$account->uuid}", [
                'components' => $componentAmounts,
            ]);

            return [
                'basket_code' => $basketCode,
                'basket_amount' => $amount,
                'components' => $componentAmounts,
                'decomposed_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Compose individual assets into a basket.
     * This is the reverse of decomposition.
     */
    public function composeBasket(Account $account, string $basketCode, int $amount): array
    {
        $basket = BasketAsset::where('code', $basketCode)->first();
        
        if (!$basket) {
            throw new \Exception("Basket not found: {$basketCode}");
        }
        
        if (!$basket->is_active) {
            throw new \Exception("Basket {$basketCode} is not active");
        }

        // Validate positive amount
        if ($amount <= 0) {
            throw new \Exception("Amount must be positive");
        }

        // Validate basket weights
        if (!$basket->validateWeights()) {
            throw new \Exception("Basket {$basketCode} has invalid component weights");
        }

        return DB::transaction(function () use ($account, $basket, $basketCode, $amount) {
            // Calculate required component amounts
            $requiredAmounts = $this->calculateComponentAmounts($basket, $amount);

            // Verify account has sufficient component balances
            foreach ($requiredAmounts as $assetCode => $requiredAmount) {
                $balance = $account->balances()
                    ->where('asset_code', $assetCode)
                    ->first();

                if (!$balance || $balance->balance < $requiredAmount) {
                    throw new \Exception("Insufficient {$assetCode} balance. Required: {$requiredAmount}, Available: " . ($balance ? $balance->balance : 0));
                }
            }

            // Use WalletService for proper Service → Workflow → Activity → Aggregate architecture
            $accountUuid = AccountUuid::fromString($account->uuid);
            
            // Subtract component balances using WalletService
            foreach ($requiredAmounts as $assetCode => $requiredAmount) {
                $this->walletService->withdraw($accountUuid, $assetCode, $requiredAmount);
            }

            // Add basket balance using WalletService
            $this->walletService->deposit($accountUuid, $basketCode, $amount);

            Log::info("Composed {$amount} of basket {$basketCode} for account {$account->uuid}", [
                'components_used' => $requiredAmounts,
            ]);

            return [
                'basket_code' => $basketCode,
                'basket_amount' => $amount,
                'components_used' => $requiredAmounts,
                'composed_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Calculate component amounts based on basket weights.
     */
    private function calculateComponentAmounts(BasketAsset $basket, int $basketAmount): array
    {
        $componentAmounts = [];
        $components = $basket->activeComponents;

        foreach ($components as $component) {
            // Calculate proportional amount based on weight
            $componentAmount = (int) round($basketAmount * ($component->weight / 100));
            $componentAmounts[$component->asset_code] = $componentAmount;
        }

        return $componentAmounts;
    }

    /**
     * Get the current value of basket holdings for an account.
     */
    public function getBasketHoldingsValue(Account $account): array
    {
        // Get balances for assets that have corresponding basket assets
        $basketCodes = BasketAsset::pluck('code');
        $basketBalances = $account->balances()
            ->whereIn('asset_code', $basketCodes)
            ->get();

        $holdings = [];
        $totalValue = 0.0;

        foreach ($basketBalances as $balance) {
            if ($balance->balance <= 0) {
                continue;
            }

            $basket = BasketAsset::where('code', $balance->asset_code)->first();
            if (!$basket) {
                continue;
            }

            $basketValue = $this->valueCalculationService->calculateValue($basket);
            $holdingValue = $basketValue->value * $balance->balance;

            $holdings[] = [
                'basket_code' => $balance->asset_code,
                'basket_name' => $basket->name,
                'balance' => $balance->balance,
                'unit_value' => $basketValue->value,
                'total_value' => $holdingValue,
                'last_calculated' => $basketValue->calculated_at->toISOString(),
            ];

            $totalValue += $holdingValue;
        }

        return [
            'account_uuid' => (string) $account->uuid,
            'basket_holdings' => $holdings,
            'total_value' => $totalValue,
            'currency' => 'USD',
            'calculated_at' => now()->toISOString(),
        ];
    }

    /**
     * Calculate the required component amounts for a given basket amount.
     * Used for planning basket compositions.
     */
    public function calculateRequiredComponents(string $basketCode, int $amount): array
    {
        $basket = BasketAsset::where('code', $basketCode)->first();
        
        if (!$basket) {
            throw new \Exception("Basket not found: {$basketCode}");
        }
        $components = $basket->activeComponents;
        $required = [];

        foreach ($components as $component) {
            $requiredAmount = (int) round($amount * ($component->weight / 100));
            $required[$component->asset_code] = $requiredAmount;
        }

        return $required;
    }
}