<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Activities;

use App\Domain\Exchange\Services\OrderService;
use App\Models\Order;
use Illuminate\Support\Str;
use Workflow\Activity\ActivityInterface;
use Workflow\Activity\ActivityMethod;

#[ActivityInterface]
class PlaceOrderActivity
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    /**
     * Place an order in the order book.
     *
     * @param array $orderData Order details including type, side, currencies, amount, price
     * @return string Order ID
     */
    #[ActivityMethod]
    public function execute(array $orderData): string
    {
        $orderId = Str::uuid()->toString();

        $order = new Order([
            'id'             => $orderId,
            'user_id'        => $orderData['user_id'] ?? 'market-maker',
            'type'           => $orderData['type'],
            'side'           => $orderData['side'],
            'base_currency'  => $orderData['base_currency'],
            'quote_currency' => $orderData['quote_currency'],
            'amount'         => $orderData['amount'],
            'price'          => $orderData['price'] ?? null,
            'status'         => 'pending',
            'pool_id'        => $orderData['pool_id'] ?? null,
        ]);

        $this->orderService->placeOrder($order);

        return $orderId;
    }
}
