<?php

namespace App\Domain\Exchange\Contracts;

interface ExchangeServiceInterface
{
    /**
     * Place a new order on the exchange
     *
     * @param string $accountId
     * @param string $type
     * @param string $orderType
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @param string $amount
     * @param string|null $price
     * @param string|null $stopPrice
     * @param array $metadata
     * @return array
     */
    public function placeOrder(
        string $accountId,
        string $type,
        string $orderType,
        string $baseCurrency,
        string $quoteCurrency,
        string $amount,
        ?string $price = null,
        ?string $stopPrice = null,
        array $metadata = []
    ): array;

    /**
     * Cancel an existing order
     *
     * @param string $orderId
     * @param string $reason
     * @return array
     */
    public function cancelOrder(string $orderId, string $reason = 'User requested'): array;

    /**
     * Get order book data for a currency pair
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @param int $depth
     * @return array
     */
    public function getOrderBook(string $baseCurrency, string $quoteCurrency, int $depth = 20): array;

    /**
     * Get market data and statistics for a currency pair
     *
     * @param string $baseCurrency
     * @param string $quoteCurrency
     * @return array
     */
    public function getMarketData(string $baseCurrency, string $quoteCurrency): array;
}