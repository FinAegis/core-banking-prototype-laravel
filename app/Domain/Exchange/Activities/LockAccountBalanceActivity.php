<?php

namespace App\Domain\Exchange\Activities;

use App\Models\AssetBalance;
use Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Workflow\Activity;

class LockAccountBalanceActivity extends Activity
{
    public function execute(string $orderId, object $order): object
    {
        return DB::transaction(
            function () use ($orderId, $order) {
                $lockId = Str::uuid()->toString();

                // For buy orders, lock quote currency
                // For sell orders, lock base currency
                $currencyToLock = $order->type === 'buy' ? $order->quote_currency : $order->base_currency;

                // Calculate amount to lock
                if ($order->type === 'buy') {
                    // For buy orders, lock quote amount = price * amount
                    if ($order->order_type === 'market') {
                        // For market orders, estimate using best ask + 10% slippage
                        $orderBook = \App\Domain\Exchange\Projections\OrderBook::forPair(
                            $order->base_currency,
                            $order->quote_currency
                        )->first();

                        $estimatedPrice = $orderBook && $orderBook->best_ask
                            ? bcmul($orderBook->best_ask, '1.1', 18) // 10% slippage buffer
                            : '0';

                        if (bccomp($estimatedPrice, '0', 18) === 0) {
                            return (object) [
                                'success' => false,
                                'message' => 'No market price available',
                            ];
                        }

                        $amountToLock = bcmul($order->remaining_amount, $estimatedPrice, 18);
                    } else {
                        // For limit orders, use the specified price
                        $amountToLock = bcmul($order->remaining_amount, $order->price, 18);
                    }
                } else {
                    // For sell orders, lock the base amount
                    $amountToLock = $order->remaining_amount;
                }

                // Get balance and lock it
                $balance = AssetBalance::where('account_id', $order->account_id)
                ->where('asset_code', $currencyToLock)
                ->lockForUpdate()
                ->first();

                if (! $balance) {
                    return (object) [
                    'success' => false,
                    'message' => "No {$currencyToLock} balance found",
                    ];
                }

                $availableBalance = bcsub($balance->balance, $balance->locked_balance ?? '0', 18);

                if (bccomp($availableBalance, $amountToLock, 18) < 0) {
                    return (object) [
                    'success'   => false,
                    'message'   => "Insufficient {$currencyToLock} balance",
                    'required'  => $amountToLock,
                    'available' => $availableBalance,
                    ];
                }

                // Lock the balance
                $balance->locked_balance = bcadd($balance->locked_balance ?? '0', $amountToLock, 18);
                $balance->save();

                // Store lock information for later release
                Cache::put(
                    "order_lock:{$lockId}",
                    [
                    'order_id'   => $orderId,
                    'account_id' => $order->account_id,
                    'currency'   => $currencyToLock,
                    'amount'     => $amountToLock,
                    'created_at' => now(),
                    ],
                    now()->addHours(24)
                );

                return (object) [
                'success'      => true,
                'lockId'       => $lockId,
                'amountLocked' => $amountToLock,
                'currency'     => $currencyToLock,
                ];
            }
        );
    }
}
