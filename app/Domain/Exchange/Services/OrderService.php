<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Aggregates\Order;

class OrderService
{
    public function __construct(
        private readonly ExchangeService $exchangeService
    ) {
    }

    /**
     * Place a new order.
     *
     * @param  string $accountId
     * @param  string $type          BUY or SELL
     * @param  string $baseCurrency
     * @param  string $quoteCurrency
     * @param  string $price
     * @param  string $quantity
     * @param  string $orderType     MARKET or LIMIT
     * @param  array  $metadata
     * @return array
     */
    public function placeOrder(
        string $accountId,
        string $type,
        string $baseCurrency,
        string $quoteCurrency,
        string $price,
        string $quantity,
        string $orderType = 'LIMIT',
        array $metadata = []
    ): array {
        // Calculate amount based on quantity and price
        $amount = bcmul($quantity, '1', 18); // Quantity is the amount for limit orders

        // Delegate to ExchangeService for full order processing
        return $this->exchangeService->placeOrder(
            accountId: $accountId,
            type: $type,
            baseCurrency: $baseCurrency,
            quoteCurrency: $quoteCurrency,
            amount: $amount,
            orderType: $orderType,
            price: $orderType === 'LIMIT' ? $price : null,
            metadata: $metadata
        );
    }

    public function createOrder(array $data): array
    {
        return [
            'id'     => uniqid(),
            'status' => 'created',
        ];
    }

    public function updateOrder(string $orderId, array $data): bool
    {
        return true;
    }

    public function cancelOrder(string $orderId): bool
    {
        return true;
    }

    public function getOrder(string $orderId): ?array
    {
        return [
            'id'     => $orderId,
            'status' => 'open',
        ];
    }

    /**
     * Update order with routing information.
     */
    public function updateOrderRouting(string $orderId, string $poolId, float $effectivePrice): void
    {
        // This would update the order with routing details
        // For now, this is a stub implementation
    }

    /**
     * Create a child order for split routing.
     */
    public function createChildOrder(
        string $childOrderId,
        string $parentOrderId,
        string $poolId,
        float $amount,
        float $estimatedPrice
    ): void {
        // This would create a child order linked to the parent
        // For now, this is a stub implementation
    }

    /**
     * Reject an order due to failure.
     */
    public function rejectOrder(string $orderId, string $reason): void
    {
        // This would mark the order as rejected with a reason
        // For now, this is a stub implementation
    }
}
