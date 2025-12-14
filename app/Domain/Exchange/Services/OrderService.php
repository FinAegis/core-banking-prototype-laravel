<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Projections\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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

    /**
     * Create a new order in the system.
     */
    public function createOrder(array $data): array
    {
        $orderId = 'order_' . Str::uuid()->toString();

        $order = Order::create([
            'order_id'       => $orderId,
            'account_id'     => $data['account_id'] ?? null,
            'type'           => $data['type'] ?? 'buy',
            'order_type'     => $data['order_type'] ?? 'limit',
            'base_currency'  => $data['base_currency'] ?? 'BTC',
            'quote_currency' => $data['quote_currency'] ?? 'EUR',
            'amount'         => $data['amount'] ?? '0',
            'filled_amount'  => '0',
            'price'          => $data['price'] ?? '0',
            'stop_price'     => $data['stop_price'] ?? null,
            'average_price'  => '0',
            'status'         => 'pending',
            'metadata'       => $data['metadata'] ?? [],
        ]);

        Log::info('Order created', ['order_id' => $orderId]);

        return [
            'id'         => $orderId,
            'status'     => 'created',
            'order'      => $order->toArray(),
            'created_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Update an existing order.
     */
    public function updateOrder(string $orderId, array $data): bool
    {
        $order = Order::where('order_id', $orderId)->first();

        if (! $order) {
            Log::warning('Order not found for update', ['order_id' => $orderId]);

            return false;
        }

        // Only allow updates to open orders
        if (! $order->isOpen()) {
            Log::warning('Cannot update non-open order', [
                'order_id' => $orderId,
                'status'   => $order->status,
            ]);

            return false;
        }

        // Filter allowed fields for update
        $allowedFields = ['price', 'stop_price', 'metadata'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        if (empty($updateData)) {
            return true;
        }

        $order->update($updateData);

        Log::info('Order updated', [
            'order_id' => $orderId,
            'fields'   => array_keys($updateData),
        ]);

        return true;
    }

    /**
     * Cancel an order.
     */
    public function cancelOrder(string $orderId): bool
    {
        $order = Order::where('order_id', $orderId)->first();

        if (! $order) {
            Log::warning('Order not found for cancellation', ['order_id' => $orderId]);

            return false;
        }

        if (! $order->canBeCancelled()) {
            Log::warning('Order cannot be cancelled', [
                'order_id' => $orderId,
                'status'   => $order->status,
            ]);

            return false;
        }

        $order->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'metadata'     => array_merge($order->metadata ?? [], [
                'cancelled_reason' => 'user_request',
            ]),
        ]);

        Log::info('Order cancelled', ['order_id' => $orderId]);

        return true;
    }

    /**
     * Get an order by ID.
     */
    public function getOrder(string $orderId): ?array
    {
        $order = Order::where('order_id', $orderId)->first();

        if (! $order) {
            return null;
        }

        return [
            'id'                => $order->order_id,
            'account_id'        => $order->account_id,
            'type'              => $order->type,
            'order_type'        => $order->order_type,
            'base_currency'     => $order->base_currency,
            'quote_currency'    => $order->quote_currency,
            'pair'              => $order->pair,
            'amount'            => $order->amount,
            'filled_amount'     => $order->filled_amount,
            'remaining_amount'  => $order->remaining_amount,
            'price'             => $order->price,
            'stop_price'        => $order->stop_price,
            'average_price'     => $order->average_price,
            'status'            => $order->status,
            'filled_percentage' => $order->filled_percentage,
            'trades'            => $order->trades ?? [],
            'metadata'          => $order->metadata ?? [],
            'created_at'        => $order->created_at?->toIso8601String(),
            'cancelled_at'      => $order->cancelled_at?->toIso8601String(),
            'filled_at'         => $order->filled_at?->toIso8601String(),
        ];
    }

    /**
     * Update order with routing information.
     */
    public function updateOrderRouting(string $orderId, string $poolId, float $effectivePrice): void
    {
        $order = Order::where('order_id', $orderId)->first();

        if (! $order) {
            Log::warning('Order not found for routing update', ['order_id' => $orderId]);

            return;
        }

        $rawMetadata = $order->metadata;
        if (is_array($rawMetadata)) {
            $metadata = $rawMetadata;
        } elseif (is_string($rawMetadata)) {
            $metadata = json_decode($rawMetadata, true) ?? [];
        } else {
            $metadata = [];
        }
        $metadata['routing'] = [
            'pool_id'         => $poolId,
            'effective_price' => $effectivePrice,
            'routed_at'       => now()->toIso8601String(),
        ];

        $order->update(['metadata' => $metadata]);

        Log::info('Order routing updated', [
            'order_id'        => $orderId,
            'pool_id'         => $poolId,
            'effective_price' => $effectivePrice,
        ]);
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
        $parentOrder = Order::where('order_id', $parentOrderId)->first();

        if (! $parentOrder) {
            Log::warning('Parent order not found for child order creation', [
                'parent_order_id' => $parentOrderId,
            ]);

            return;
        }

        Order::create([
            'order_id'       => $childOrderId,
            'account_id'     => $parentOrder->account_id,
            'type'           => $parentOrder->type,
            'order_type'     => 'limit',
            'base_currency'  => $parentOrder->base_currency,
            'quote_currency' => $parentOrder->quote_currency,
            'amount'         => (string) $amount,
            'filled_amount'  => '0',
            'price'          => (string) $estimatedPrice,
            'average_price'  => '0',
            'status'         => 'pending',
            'metadata'       => [
                'parent_order_id' => $parentOrderId,
                'pool_id'         => $poolId,
                'is_child_order'  => true,
            ],
        ]);

        // Update parent order metadata to track child orders
        $parentMetadata = $parentOrder->metadata ?? [];
        $parentMetadata['child_orders'] = $parentMetadata['child_orders'] ?? [];
        $parentMetadata['child_orders'][] = [
            'child_order_id'  => $childOrderId,
            'pool_id'         => $poolId,
            'amount'          => $amount,
            'estimated_price' => $estimatedPrice,
            'created_at'      => now()->toIso8601String(),
        ];
        $parentOrder->update(['metadata' => $parentMetadata]);

        Log::info('Child order created', [
            'child_order_id'  => $childOrderId,
            'parent_order_id' => $parentOrderId,
            'pool_id'         => $poolId,
            'amount'          => $amount,
        ]);
    }

    /**
     * Reject an order due to failure.
     */
    public function rejectOrder(string $orderId, string $reason): void
    {
        $order = Order::where('order_id', $orderId)->first();

        if (! $order) {
            Log::warning('Order not found for rejection', ['order_id' => $orderId]);

            return;
        }

        $rawMetadata = $order->metadata;
        if (is_array($rawMetadata)) {
            $metadata = $rawMetadata;
        } elseif (is_string($rawMetadata)) {
            $metadata = json_decode($rawMetadata, true) ?? [];
        } else {
            $metadata = [];
        }
        $metadata['rejection'] = [
            'reason'      => $reason,
            'rejected_at' => now()->toIso8601String(),
        ];

        $order->update([
            'status'   => 'rejected',
            'metadata' => $metadata,
        ]);

        Log::info('Order rejected', [
            'order_id' => $orderId,
            'reason'   => $reason,
        ]);
    }
}
