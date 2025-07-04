<?php

namespace App\Domain\Exchange\Contracts;

use App\Domain\Exchange\Aggregates\Order;
use App\Domain\Exchange\ValueObjects\OrderBookUpdate;

interface OrderMatchingServiceInterface
{
    /**
     * Match orders in the order book
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @param int $maxIterations
     * @return array
     */
    public function matchOrders(string $baseCurrency, string $quoteCurrency, int $maxIterations = 100): array;

    /**
     * Try to match a specific order
     *
     * @param Order $order
     * @param OrderBookUpdate $orderBookUpdate
     * @return array
     */
    public function tryMatchOrder(Order $order, OrderBookUpdate $orderBookUpdate): array;

    /**
     * Execute a trade between two orders
     *
     * @param Order $buyOrder
     * @param Order $sellOrder
     * @param string $executionPrice
     * @param string $executionAmount
     * @return void
     */
    public function executeTrade(
        Order $buyOrder,
        Order $sellOrder,
        string $executionPrice,
        string $executionAmount
    ): void;

    /**
     * Calculate execution price for a trade
     *
     * @param Order $takerOrder
     * @param Order $makerOrder
     * @return string
     */
    public function calculateExecutionPrice(Order $takerOrder, Order $makerOrder): string;

    /**
     * Validate if two orders can be matched
     *
     * @param Order $buyOrder
     * @param Order $sellOrder
     * @return bool
     */
    public function canMatch(Order $buyOrder, Order $sellOrder): bool;
}