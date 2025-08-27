<?php

namespace App\Domain\Exchange\Workflows;

use App\Domain\Exchange\Activities\LockAccountBalanceActivity;
use App\Domain\Exchange\Activities\MatchOrderActivity;
use App\Domain\Exchange\Activities\ReleaseAccountBalanceActivity;
use App\Domain\Exchange\Activities\TransferAssetsActivity;
use App\Domain\Exchange\Activities\UpdateOrderBookActivity;
use App\Domain\Exchange\Activities\ValidateOrderActivity;
use App\Domain\Exchange\Aggregates\Order;
use App\Domain\Exchange\ValueObjects\OrderMatchingInput;
use App\Domain\Exchange\ValueObjects\OrderMatchingResult;
use Carbon\CarbonInterval;
use Exception;
use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class OrderMatchingWorkflow extends Workflow
{
    public function execute(OrderMatchingInput $input): Generator
    {
        // Step 1: Validate order
        $validationResult = yield ActivityStub::make(ValidateOrderActivity::class, $input->orderId);

        if (! $validationResult->isValid) {
            return new OrderMatchingResult(
                success: false,
                message: $validationResult->message,
                orderId: $input->orderId
            );
        }

        // Step 2: Lock account balance for the order
        $lockResult = yield ActivityStub::make(
            LockAccountBalanceActivity::class,
            $input->orderId,
            $validationResult->order
        );

        // Add compensation to release balance if something goes wrong
        $this->addCompensation(
            fn () => ActivityStub::make(
                ReleaseAccountBalanceActivity::class,
                $input->orderId,
                $lockResult->lockId
            )
        );

        if (! $lockResult->success) {
            yield $this->compensate();

            return new OrderMatchingResult(
                success: false,
                message: 'Insufficient balance',
                orderId: $input->orderId
            );
        }

        // Step 3: Add order to order book
        $orderBookResult = yield ActivityStub::make(
            UpdateOrderBookActivity::class,
            $input->orderId,
            'add'
        );

        // Add compensation to remove from order book
        $this->addCompensation(
            fn () => ActivityStub::make(
                UpdateOrderBookActivity::class,
                $input->orderId,
                'remove'
            )
        );

        // Step 4: Try to match the order
        $matchingResult = yield ActivityStub::make(
            MatchOrderActivity::class,
            $input->orderId,
            $input->maxIterations ?? 100
        );

        if ($matchingResult->matches->isEmpty()) {
            // No matches found, order stays in the book
            return new OrderMatchingResult(
                success: true,
                message: 'Order placed successfully',
                orderId: $input->orderId,
                status: 'open',
                filledAmount: '0'
            );
        }

        // Step 5: Process each match
        foreach ($matchingResult->matches as $match) {
            try {
                // Transfer assets between accounts
                $transferResult = yield ActivityStub::make(
                    TransferAssetsActivity::class,
                    $match->buyOrderId,
                    $match->sellOrderId,
                    $match->executedAmount,
                    $match->executedPrice
                );

                if (! $transferResult->success) {
                    // Log the error but continue with other matches
                    yield ActivityStub::make(
                        'App\Domain\Exchange\Activities\LogMatchingErrorActivity',
                        $match,
                        $transferResult->error
                    );

                    continue;
                }

                // Update both orders
                Order::retrieve($match->buyOrderId)->matchOrder(
                    matchedOrderId: $match->sellOrderId,
                    tradeId: $match->tradeId,
                    executedPrice: $match->executedPrice,
                    executedAmount: $match->executedAmount,
                    makerFee: $match->makerFee,
                    takerFee: $match->takerFee
                )->persist();

                Order::retrieve($match->sellOrderId)->matchOrder(
                    matchedOrderId: $match->buyOrderId,
                    tradeId: $match->tradeId,
                    executedPrice: $match->executedPrice,
                    executedAmount: $match->executedAmount,
                    makerFee: $match->makerFee,
                    takerFee: $match->takerFee
                )->persist();
            } catch (Exception $e) {
                // Log error and continue
                yield ActivityStub::make(
                    'App\Domain\Exchange\Activities\LogMatchingErrorActivity',
                    $match,
                    $e->getMessage()
                );
            }
        }

        // Step 6: Update order book to remove filled orders
        yield ActivityStub::make(
            UpdateOrderBookActivity::class,
            $input->orderId,
            'update_after_match',
            $matchingResult
        );

        // Calculate total filled amount
        $totalFilledAmount = $matchingResult->matches->reduce(
            function ($carry, $match) {
                return bcadd($carry, $match->executedAmount, 18);
            },
            '0'
        );

        $order = Order::retrieve($input->orderId);
        $status = $order->getRemainingAmount()->isZero() ? 'filled' : 'partially_filled';

        return new OrderMatchingResult(
            success: true,
            message: "Order {$status}",
            orderId: $input->orderId,
            status: $status,
            filledAmount: $totalFilledAmount,
            trades: $matchingResult->matches->map(fn ($m) => $m->tradeId)->toArray()
        );
    }

    protected function getCompensationDelay(): CarbonInterval
    {
        return CarbonInterval::minutes(5);
    }
}
