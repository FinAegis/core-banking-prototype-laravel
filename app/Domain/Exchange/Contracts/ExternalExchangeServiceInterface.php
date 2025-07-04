<?php

namespace App\Domain\Exchange\Contracts;

interface ExternalExchangeServiceInterface
{
    /**
     * Connect to an external exchange
     *
     * @param string $exchange
     * @param array $credentials
     * @return void
     */
    public function connect(string $exchange, array $credentials): void;

    /**
     * Get market data from external exchange
     *
     * @param string $exchange
     * @param string $symbol
     * @return array
     */
    public function getMarketData(string $exchange, string $symbol): array;

    /**
     * Place order on external exchange
     *
     * @param string $exchange
     * @param array $orderData
     * @return array
     */
    public function placeOrder(string $exchange, array $orderData): array;

    /**
     * Get order status from external exchange
     *
     * @param string $exchange
     * @param string $orderId
     * @return array
     */
    public function getOrderStatus(string $exchange, string $orderId): array;

    /**
     * Cancel order on external exchange
     *
     * @param string $exchange
     * @param string $orderId
     * @return array
     */
    public function cancelOrder(string $exchange, string $orderId): array;

    /**
     * Get account balance from external exchange
     *
     * @param string $exchange
     * @return array
     */
    public function getBalance(string $exchange): array;

    /**
     * Sync trades from external exchange
     *
     * @param string $exchange
     * @param array $filters
     * @return array
     */
    public function syncTrades(string $exchange, array $filters = []): array;

    /**
     * Get supported exchanges
     *
     * @return array
     */
    public function getSupportedExchanges(): array;
}