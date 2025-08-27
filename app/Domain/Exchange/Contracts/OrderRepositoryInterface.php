<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Contracts;

use App\Domain\Exchange\Aggregates\Order;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Repository interface for Order aggregate persistence.
 */
interface OrderRepositoryInterface
{
    /**
     * Find an order by ID.
     */
    public function find(string $orderId): ?Order;

    /**
     * Find all orders by account.
     */
    public function findByAccount(string $accountId): Collection;

    /**
     * Find open orders for a currency pair.
     */
    public function findOpenOrders(string $baseCurrency, string $quoteCurrency): Collection;

    /**
     * Save an order aggregate.
     */
    public function save(Order $order): void;

    /**
     * Delete an order.
     */
    public function delete(string $orderId): void;

    /**
     * Find orders by status.
     */
    public function findByStatus(string $status): Collection;

    /**
     * Find orders placed within a time range.
     */
    public function findByTimeRange(DateTimeInterface $from, DateTimeInterface $to): Collection;

    /**
     * Get order statistics for a currency pair.
     */
    public function getStatistics(string $baseCurrency, string $quoteCurrency): array;
}
