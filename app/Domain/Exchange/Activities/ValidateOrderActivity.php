<?php

namespace App\Domain\Exchange\Activities;

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Exchange\Aggregates\Order;
use App\Domain\Exchange\Projections\Order as OrderProjection;
use Workflow\Activity;

class ValidateOrderActivity extends Activity
{
    public function execute(string $orderId): object
    {
        /** @var mixed|null $quoteCurrency */
        $quoteCurrency = null;
        /** @var mixed|null $baseCurrency */
        $baseCurrency = null;
        /** @var mixed|null $orderProjection */
        $orderProjection = null;
        /** @var Account|null $account */
        $account = null;
        /** @var \Illuminate\Database\Eloquent\Model|null $orderProjection */
        $orderProjection = OrderProjection::where('order_id', $orderId)->first();

        if (! $orderProjection) {
            return (object) [
                'isValid' => false,
                'message' => 'Order not found',
            ];
        }

        // Check if order is already filled or cancelled
        if (! $orderProjection->isOpen()) {
            return (object) [
                'isValid' => false,
                'message' => "Order is {$orderProjection->status}",
            ];
        }

        // Validate account exists
        /** @var Account|null $account */
        $account = Account::find($orderProjection->account_id);
        if (! $account) {
            return (object) [
                'isValid' => false,
                'message' => 'Account not found',
            ];
        }

        // Validate currencies exist
        /** @var \Illuminate\Database\Eloquent\Model|null $baseCurrency */
        $baseCurrency = Asset::where('code', $orderProjection->base_currency)->first();
        /** @var \Illuminate\Database\Eloquent\Model|null $quoteCurrency */
        $quoteCurrency = Asset::where('code', $orderProjection->quote_currency)->first();

        if (! $baseCurrency || ! $quoteCurrency) {
            return (object) [
                'isValid' => false,
                'message' => 'Invalid currency pair',
            ];
        }

        // Check if currencies are enabled for trading
        if (! $baseCurrency->is_tradeable || ! $quoteCurrency->is_tradeable) {
            return (object) [
                'isValid' => false,
                'message' => 'Currency pair not available for trading',
            ];
        }

        // Validate order amounts
        if (bccomp($orderProjection->remaining_amount, '0', 18) <= 0) {
            return (object) [
                'isValid' => false,
                'message' => 'Invalid order amount',
            ];
        }

        // For limit orders, validate price
        if ($orderProjection->order_type === 'limit' && bccomp($orderProjection->price ?? '0', '0', 18) <= 0) {
            return (object) [
                'isValid' => false,
                'message' => 'Invalid limit price',
            ];
        }

        return (object) [
            'isValid'       => true,
            'message'       => 'Order is valid',
            'order'         => $orderProjection,
            'account'       => $account,
            'baseCurrency'  => $baseCurrency,
            'quoteCurrency' => $quoteCurrency,
        ];
    }
}
