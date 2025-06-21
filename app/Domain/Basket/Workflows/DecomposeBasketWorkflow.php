<?php

declare(strict_types=1);

namespace App\Domain\Basket\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Basket\Services\BasketAccountService;
use App\Models\Account;
use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\Activity;

final class DecomposeBasketWorkflow extends Workflow
{
    /**
     * Decompose a basket into its component assets with compensation logic.
     *
     * @param AccountUuid $accountUuid
     * @param string $basketCode
     * @param int $amount
     * @return \Generator
     */
    public function execute(
        AccountUuid $accountUuid,
        string $basketCode,
        int $amount
    ): \Generator {
        try {
            // Validate inputs
            yield ActivityStub::make(ValidateBasketDecompositionActivity::class, $accountUuid, $basketCode, $amount);

            // Perform decomposition and store result for potential compensation
            $decompositionResult = yield ActivityStub::make(DecomposeBasketActivity::class, $accountUuid, $basketCode, $amount);
            
            // Add compensation to reverse the decomposition if needed
            $this->addCompensation(fn() => ActivityStub::make(
                ReverseBasketDecompositionActivity::class, 
                $accountUuid, 
                $basketCode, 
                $amount,
                $decompositionResult
            ));
            
            return $decompositionResult;
        } catch (\Throwable $th) {
            // Execute compensations in reverse order
            yield from $this->compensate();
            
            throw $th;
        }
    }
}

/**
 * Activity to validate basket decomposition request.
 */
class ValidateBasketDecompositionActivity extends Activity
{
    public function execute(AccountUuid $accountUuid, string $basketCode, int $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero');
        }

        $account = Account::where('uuid', $accountUuid->__toString())->firstOrFail();
        
        if ($account->frozen) {
            throw new \Exception('Account is frozen');
        }

        // Additional validation can be added here
    }
}

/**
 * Activity to perform basket decomposition.
 */
class DecomposeBasketActivity extends Activity
{
    public function __construct(
        private readonly BasketAccountService $basketAccountService
    ) {}

    public function execute(AccountUuid $accountUuid, string $basketCode, int $amount): array
    {
        $account = Account::where('uuid', $accountUuid->__toString())->firstOrFail();
        
        return $this->basketAccountService->decomposeBasket($account, $basketCode, $amount);
    }
}

/**
 * Activity to reverse basket decomposition as part of compensation.
 */
class ReverseBasketDecompositionActivity extends Activity
{
    public function __construct(
        private readonly BasketAccountService $basketAccountService
    ) {}

    public function execute(
        AccountUuid $accountUuid, 
        string $basketCode, 
        int $amount,
        array $decompositionResult
    ): void {
        $account = Account::where('uuid', $accountUuid->__toString())->firstOrFail();
        
        // Reverse the decomposition by composing back the basket from components
        // This effectively undoes the decomposition operation
        $this->basketAccountService->composeBasket($account, $basketCode, $amount);
        
        logger()->info('Basket decomposition reversed', [
            'account_uuid' => $accountUuid->__toString(),
            'basket_code' => $basketCode,
            'amount' => $amount,
            'original_result' => $decompositionResult,
            'reason' => 'Workflow compensation',
        ]);
    }
}