<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Sagas;

use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use App\Domain\Exchange\ValueObjects\OrderMatchingInput;
use App\Domain\Exchange\Workflows\OrderMatchingWorkflow;
use App\Domain\Payment\Workflows\TransferWorkflow;
use App\Domain\Wallet\Workflows\WalletTransferWorkflow;
use Illuminate\Support\Facades\Log;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

/**
 * Saga for orchestrating order fulfillment across multiple domains.
 * Coordinates Exchange, Account, Wallet, and Payment domains with full compensation support.
 */
class OrderFulfillmentSaga extends Workflow
{
    private array $compensations = [];

    private array $completedSteps = [];

    /**
     * Execute the order fulfillment saga.
     *
     * @param array $input Contains:
     *   - order_id: string
     *   - buyer_account_id: string
     *   - seller_account_id: string
     *   - base_currency: string
     *   - quote_currency: string
     *   - amount: float
     *   - price: float
     *   - type: 'buy' | 'sell'
     */
    public function execute(array $input): \Generator
    {
        $sagaId = \Str::uuid()->toString();

        Log::info('Starting OrderFulfillmentSaga', [
            'saga_id'  => $sagaId,
            'order_id' => $input['order_id'],
        ]);

        try {
            // Step 1: Lock buyer's funds
            $lockResult = yield from $this->lockBuyerFunds($input);
            if (! $lockResult['success']) {
                throw new \Exception('Failed to lock buyer funds: ' . $lockResult['message']);
            }
            $this->completedSteps[] = 'lock_buyer_funds';

            // Step 2: Match the order
            $matchResult = yield from $this->matchOrder($input);
            if (! $matchResult->success) {
                throw new \Exception('Order matching failed: ' . $matchResult->message);
            }
            $this->completedSteps[] = 'match_order';

            // Step 3: Transfer assets from seller to buyer
            $transferResult = yield from $this->transferAssets(
                $input['seller_account_id'],
                $input['buyer_account_id'],
                $input['base_currency'],
                $input['amount']
            );
            if (! $transferResult['success']) {
                throw new \Exception('Asset transfer failed: ' . $transferResult['message']);
            }
            $this->completedSteps[] = 'transfer_assets';

            // Step 4: Transfer payment from buyer to seller
            $paymentResult = yield from $this->transferPayment(
                $input['buyer_account_id'],
                $input['seller_account_id'],
                $input['quote_currency'],
                $input['amount'] * $input['price']
            );
            if (! $paymentResult['success']) {
                throw new \Exception('Payment transfer failed: ' . $paymentResult['message']);
            }
            $this->completedSteps[] = 'transfer_payment';

            // Step 5: Update order status
            $updateResult = yield from $this->updateOrderStatus($input['order_id'], 'completed');
            $this->completedSteps[] = 'update_order_status';

            Log::info('OrderFulfillmentSaga completed successfully', [
                'saga_id'         => $sagaId,
                'order_id'        => $input['order_id'],
                'completed_steps' => $this->completedSteps,
            ]);

            return [
                'success'         => true,
                'saga_id'         => $sagaId,
                'order_id'        => $input['order_id'],
                'matched_orders'  => $matchResult->matchedOrders ?? [],
                'completed_steps' => $this->completedSteps,
            ];
        } catch (\Throwable $e) {
            Log::error('OrderFulfillmentSaga failed, executing compensations', [
                'saga_id'         => $sagaId,
                'order_id'        => $input['order_id'],
                'error'           => $e->getMessage(),
                'completed_steps' => $this->completedSteps,
            ]);

            // Execute compensations in reverse order
            yield from $this->executeCompensations();

            return [
                'success'           => false,
                'saga_id'           => $sagaId,
                'order_id'          => $input['order_id'],
                'error'             => $e->getMessage(),
                'compensated_steps' => array_keys($this->compensations),
            ];
        }
    }

    /**
     * Lock buyer's funds for the order.
     */
    private function lockBuyerFunds(array $input): \Generator
    {
        $workflow = yield ChildWorkflowStub::make(
            WithdrawAccountWorkflow::class
        );

        $amount = $input['amount'] * $input['price'];

        $result = yield $workflow->execute(
            $input['buyer_account_id'],
            $input['quote_currency'],
            $amount,
            "Lock funds for order {$input['order_id']}"
        );

        // Add compensation to unlock funds
        $this->registerCompensation('lock_buyer_funds', function () use ($input, $amount) {
            return ChildWorkflowStub::make(DepositAccountWorkflow::class)
                ->execute(
                    $input['buyer_account_id'],
                    $input['quote_currency'],
                    $amount,
                    "Unlock funds - order {$input['order_id']} cancelled"
                );
        });

        return $result;
    }

    /**
     * Match the order in the order book.
     */
    private function matchOrder(array $input): \Generator
    {
        $workflow = yield ChildWorkflowStub::make(
            OrderMatchingWorkflow::class
        );

        $matchingInput = new OrderMatchingInput(
            orderId: $input['order_id'],
            maxIterations: 100
        );

        $result = yield $workflow->execute($matchingInput);

        // Add compensation to cancel the order
        $this->registerCompensation('match_order', function () use ($input) {
            return $this->updateOrderStatus($input['order_id'], 'cancelled');
        });

        return $result;
    }

    /**
     * Transfer assets from seller to buyer.
     */
    private function transferAssets(
        string $fromAccount,
        string $toAccount,
        string $currency,
        float $amount
    ): \Generator {
        $workflow = yield ChildWorkflowStub::make(
            WalletTransferWorkflow::class
        );

        $result = yield $workflow->execute(
            $fromAccount,
            $toAccount,
            $currency,
            $amount
        );

        // Add compensation to reverse the transfer
        $this->registerCompensation('transfer_assets', function () use ($toAccount, $fromAccount, $currency, $amount) {
            return ChildWorkflowStub::make(WalletTransferWorkflow::class)
                ->execute(
                    $toAccount,
                    $fromAccount,
                    $currency,
                    $amount
                );
        });

        return $result;
    }

    /**
     * Transfer payment from buyer to seller.
     */
    private function transferPayment(
        string $fromAccount,
        string $toAccount,
        string $currency,
        float $amount
    ): \Generator {
        $workflow = yield ChildWorkflowStub::make(
            TransferWorkflow::class
        );

        $result = yield $workflow->execute([
            'from_account' => $fromAccount,
            'to_account'   => $toAccount,
            'currency'     => $currency,
            'amount'       => $amount,
            'description'  => 'Order fulfillment payment',
        ]);

        // Add compensation to reverse the payment
        $this->registerCompensation('transfer_payment', function () use ($toAccount, $fromAccount, $currency, $amount) {
            return ChildWorkflowStub::make(TransferWorkflow::class)
                ->execute([
                    'from_account' => $toAccount,
                    'to_account'   => $fromAccount,
                    'currency'     => $currency,
                    'amount'       => $amount,
                    'description'  => 'Order fulfillment payment reversal',
                ]);
        });

        return $result;
    }

    /**
     * Update order status.
     */
    private function updateOrderStatus(string $orderId, string $status): \Generator
    {
        // This would typically call an activity to update the order
        // For now, we'll simulate it without delay
        yield;

        return [
            'success'  => true,
            'order_id' => $orderId,
            'status'   => $status,
        ];
    }

    /**
     * Register a compensation action.
     */
    private function registerCompensation(string $step, callable $compensation): void
    {
        $this->compensations[$step] = $compensation;
    }

    /**
     * Execute all compensations in reverse order.
     */
    private function executeCompensations(): \Generator
    {
        $compensations = array_reverse($this->compensations, true);

        foreach ($compensations as $step => $compensation) {
            try {
                Log::info("Executing compensation for step: {$step}");
                yield $compensation();
                Log::info("Compensation successful for step: {$step}");
            } catch (\Throwable $e) {
                Log::error("Compensation failed for step: {$step}", [
                    'error' => $e->getMessage(),
                ]);
                // Continue with other compensations even if one fails
            }
        }
    }
}
