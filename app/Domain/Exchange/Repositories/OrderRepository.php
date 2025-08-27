<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Repositories;

use App\Domain\Exchange\Aggregates\Order;
use App\Domain\Exchange\Contracts\OrderRepositoryInterface;
use App\Domain\Exchange\Projections\Order as OrderProjection;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Collection;

/**
 * Event-sourced repository implementation for Order aggregates.
 */
class OrderRepository implements OrderRepositoryInterface
{
    /**
     * Find an order by ID.
     */
    public function find(string $orderId): ?Order
    {
        try {
            return Order::retrieve($orderId);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Find all orders by account.
     */
    public function findByAccount(string $accountId): Collection
    {
        // Query the projections table for orders by account
        return OrderProjection::where('account_id', $accountId)
            ->get()
            ->map(fn ($projection) => $this->find($projection->order_id))
            ->filter();
    }

    /**
     * Find open orders for a currency pair.
     */
    public function findOpenOrders(string $baseCurrency, string $quoteCurrency): Collection
    {
        return OrderProjection::where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency)
            ->whereIn('status', ['pending', 'open', 'partially_filled'])
            ->get()
            ->map(fn ($projection) => $this->find($projection->order_id))
            ->filter();
    }

    /**
     * Save an order aggregate.
     */
    public function save(Order $order): void
    {
        $order->persist();
    }

    /**
     * Delete an order.
     */
    public function delete(string $orderId): void
    {
        $order = $this->find($orderId);
        if ($order) {
            // Cancel the order
            $order->cancelOrder('Order deleted', ['deleted_at' => now()]);
            $order->persist();
        }
    }

    /**
     * Find orders by status.
     */
    public function findByStatus(string $status): Collection
    {
        return OrderProjection::where('status', $status)
            ->get()
            ->map(fn ($projection) => $this->find($projection->order_id))
            ->filter();
    }

    /**
     * Find orders placed within a time range.
     */
    public function findByTimeRange(DateTimeInterface $from, DateTimeInterface $to): Collection
    {
        return OrderProjection::whereBetween('created_at', [$from, $to])
            ->get()
            ->map(fn ($projection) => $this->find($projection->order_id))
            ->filter();
    }

    /**
     * Get order statistics for a currency pair.
     */
    public function getStatistics(string $baseCurrency, string $quoteCurrency): array
    {
        $stats = OrderProjection::where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency)
            ->selectRaw('
                COUNT(*) as total_orders,
                COUNT(CASE WHEN status = "filled" THEN 1 END) as filled_orders,
                COUNT(CASE WHEN status = "cancelled" THEN 1 END) as cancelled_orders,
                AVG(CASE WHEN status = "filled" THEN fill_price END) as avg_fill_price,
                SUM(CASE WHEN status = "filled" THEN amount END) as total_volume,
                MIN(created_at) as first_order_at,
                MAX(created_at) as last_order_at
            ')
            ->first();

        return $stats ? $stats->toArray() : [];
    }
}
