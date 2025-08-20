<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Activities;

use App\Domain\Exchange\Services\OrderService;
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

        // Place order through service
        $this->orderService->placeOrder(
            accountId: $orderData['user_id'] ?? 'market-maker',
            type: strtoupper($orderData['side']), // side 'buy' becomes 'BUY'
            baseCurrency: $orderData['base_currency'],
            quoteCurrency: $orderData['quote_currency'],
            price: (string) ($orderData['price'] ?? '0'),
            quantity: (string) $orderData['amount'],
            orderType: strtoupper($orderData['type'] ?? 'LIMIT')
        );

        return $orderId;
    }
}
