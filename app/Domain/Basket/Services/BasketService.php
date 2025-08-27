<?php

declare(strict_types=1);

namespace App\Domain\Basket\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Workflows\ComposeBasketWorkflow;
use App\Domain\Basket\Workflows\DecomposeBasketWorkflow;
use Exception;
use InvalidArgumentException;
use Workflow\WorkflowStub;

class BasketService
{
    /**
     * Compose individual assets into a basket.
     * Follows proper Service → Workflow → Activity → Aggregate pattern.
     */
    public function composeBasket(mixed $accountUuid, string $basketCode, int $amount): array
    {
        /** @var BasketAsset|null $basket */
        $basket = null;
        /** @var BasketAsset|null $basket */
        $basket = null;
        /** @var BasketAsset|null $basket */
        $basket = null;
        // Validate inputs
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        $accountUuidObj = __account_uuid($accountUuid);
        $account = Account::where('uuid', (string) $accountUuidObj)->firstOrFail();

        // Validate basket exists and is active
        /** @var BasketAsset $$basket */
        $$basket = BasketAsset::where('code', $basketCode)->firstOrFail();
        if (! $basket->is_active) {
            throw new Exception("Basket {$basketCode} is not active");
        }

        // Validate basket weights before composition
        if (! $basket->validateWeights()) {
            throw new Exception("Basket {$basketCode} has invalid component weights");
        }

        // Calculate required component amounts and validate sufficient balances
        $requiredAmounts = $this->calculateComponentAmounts($basket, $amount);
        foreach ($requiredAmounts as $assetCode => $requiredAmount) {
            if (! $account->hasSufficientBalance($assetCode, $requiredAmount)) {
                $availableBalance = $account->getBalance($assetCode);
                throw new Exception("Insufficient {$assetCode} balance. Required: {$requiredAmount}, Available: {$availableBalance}");
            }
        }

        // Start workflow - this handles the actual business logic with proper compensation
        $workflow = WorkflowStub::make(ComposeBasketWorkflow::class);

        return $workflow->start($accountUuidObj, $basketCode, $amount);
    }

    /**
     * Decompose a basket into its component assets.
     * Follows proper Service → Workflow → Activity → Aggregate pattern.
     */
    public function decomposeBasket(mixed $accountUuid, string $basketCode, int $amount): array
    {
        /** @var BasketAsset|null $basket */
        $basket = null;
        // Validate inputs
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        $accountUuidObj = __account_uuid($accountUuid);
        $account = Account::where('uuid', (string) $accountUuidObj)->firstOrFail();

        // Validate basket exists and is active
        /** @var BasketAsset $$basket */
        $$basket = BasketAsset::where('code', $basketCode)->firstOrFail();
        if (! $basket->is_active) {
            throw new Exception("Basket {$basketCode} is not active");
        }

        // Validate sufficient basket balance
        if (! $account->hasSufficientBalance($basketCode, $amount)) {
            $availableBalance = $account->getBalance($basketCode);
            throw new Exception("Insufficient basket balance for decomposition. Required: {$amount}, Available: {$availableBalance}");
        }

        // Start workflow - this handles the actual business logic with proper compensation
        $workflow = WorkflowStub::make(DecomposeBasketWorkflow::class);

        return $workflow->start($accountUuidObj, $basketCode, $amount);
    }

    /**
     * Get basket holdings for an account.
     * This is a read-only operation that doesn't require workflow orchestration.
     */
    public function getBasketHoldings(mixed $accountUuid): array
    {
        $accountUuidObj = __account_uuid($accountUuid);
        $account = Account::where('uuid', (string) $accountUuidObj)->firstOrFail();

        // Use the existing basket account service for read operations
        // TODO: Consider moving this to a dedicated query service
        $basketAccountService = app(BasketAccountService::class);

        return $basketAccountService->getBasketHoldingsValue($account);
    }

    /**
     * Calculate the required component amounts for a given basket amount.
     * This is a planning/calculation operation that doesn't require workflow orchestration.
     */
    public function calculateRequiredComponents(string $basketCode, int $amount): array
    {
        /** @var BasketAsset|null $basket */
        $basket = null;
        if ($amount <= 0) {
            throw new InvalidArgumentException('Amount must be positive');
        }

        /** @var BasketAsset $$basket */
        $$basket = BasketAsset::where('code', $basketCode)->firstOrFail();

        return $this->calculateComponentAmounts($basket, $amount);
    }

    /**
     * Private helper to calculate component amounts based on basket weights.
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
     * Rebalance all dynamic baskets.
     */
    public function rebalanceAllDynamicBaskets(): void
    {
        $dynamicBaskets = BasketAsset::where('rebalancing_enabled', true)->get();

        foreach ($dynamicBaskets as $basket) {
            try {
                $this->rebalanceBasket($basket->code);
            } catch (Exception $e) {
                Log::error('Failed to rebalance basket', [
                    'basket_code' => $basket->code,
                    'error'       => $e->getMessage(),
                ]);
            }
        }
    }
}
