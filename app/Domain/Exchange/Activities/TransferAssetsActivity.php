<?php

namespace App\Domain\Exchange\Activities;

use App\Domain\Account\Aggregates\Account;
use App\Domain\Exchange\ValueObjects\Money;
use App\Domain\Transaction\Aggregates\Transaction;
use App\Domain\Transaction\DataTransferObjects\TransactionData;
use App\Domain\Transaction\Enums\TransactionType;
use Exception;
use Illuminate\Support\Str;
use Workflow\Activity;

class TransferAssetsActivity extends Activity
{
    public function execute(
        string $buyOrderId,
        string $sellOrderId,
        string $executedAmount,
        string $executedPrice
    ): object {
        try {
            // Get order details from projections
            $buyOrder = \App\Domain\Exchange\Projections\Order::query()
                ->where('order_id', $buyOrderId)
                ->firstOrFail();

            $sellOrder = \App\Domain\Exchange\Projections\Order::query()
                ->where('order_id', $sellOrderId)
                ->firstOrFail();

            // Calculate the amounts for each side
            $baseAmount = $executedAmount;
            $quoteAmount = bcmul($executedAmount, $executedPrice, 18);

            // Create transaction ID
            $transactionId = (string) Str::uuid();

            // Transfer base asset from seller to buyer
            $baseTransferData = new TransactionData(
                type: TransactionType::EXCHANGE,
                fromAccountId: $sellOrder->account_id,
                toAccountId: $buyOrder->account_id,
                amount: $baseAmount,
                assetCode: $sellOrder->base_asset,
                description: "Exchange trade: {$baseAmount} {$sellOrder->base_asset}",
                metadata: [
                    'trade_type'     => 'exchange',
                    'buy_order_id'   => $buyOrderId,
                    'sell_order_id'  => $sellOrderId,
                    'executed_price' => $executedPrice,
                    'side'           => 'base_transfer',
                ]
            );

            Transaction::create($transactionId, $baseTransferData)->persist();

            // Transfer quote asset from buyer to seller
            $quoteTransactionId = (string) Str::uuid();
            $quoteTransferData = new TransactionData(
                type: TransactionType::EXCHANGE,
                fromAccountId: $buyOrder->account_id,
                toAccountId: $sellOrder->account_id,
                amount: $quoteAmount,
                assetCode: $sellOrder->quote_asset,
                description: "Exchange trade: {$quoteAmount} {$sellOrder->quote_asset}",
                metadata: [
                    'trade_type'             => 'exchange',
                    'buy_order_id'           => $buyOrderId,
                    'sell_order_id'          => $sellOrderId,
                    'executed_price'         => $executedPrice,
                    'side'                   => 'quote_transfer',
                    'related_transaction_id' => $transactionId,
                ]
            );

            Transaction::create($quoteTransactionId, $quoteTransferData)->persist();

            // Process the transactions through accounts
            Account::retrieve($sellOrder->account_id)
                ->withdraw(new Money($baseAmount, $sellOrder->base_asset), $transactionId)
                ->persist();

            Account::retrieve($buyOrder->account_id)
                ->deposit(new Money($baseAmount, $sellOrder->base_asset), $transactionId)
                ->persist();

            Account::retrieve($buyOrder->account_id)
                ->withdraw(new Money($quoteAmount, $sellOrder->quote_asset), $quoteTransactionId)
                ->persist();

            Account::retrieve($sellOrder->account_id)
                ->deposit(new Money($quoteAmount, $sellOrder->quote_asset), $quoteTransactionId)
                ->persist();

            return (object) [
                'success'            => true,
                'baseTransactionId'  => $transactionId,
                'quoteTransactionId' => $quoteTransactionId,
                'baseAmount'         => $baseAmount,
                'quoteAmount'        => $quoteAmount,
            ];
        } catch (Exception $e) {
            return (object) [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }
}
