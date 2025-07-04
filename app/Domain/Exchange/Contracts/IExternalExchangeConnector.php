<?php

namespace App\Domain\Exchange\Contracts;

use App\Domain\Exchange\ValueObjects\ExternalOrderBook;
use App\Domain\Exchange\ValueObjects\ExternalTicker;
use App\Domain\Exchange\ValueObjects\ExternalTrade;
use App\Domain\Exchange\ValueObjects\MarketPair;
use Illuminate\Support\Collection;

interface IExternalExchangeConnector
{
    /**
     * Get the exchange name
     */
    public function getName(): string;

    /**
     * Check if the exchange is available
     */
    public function isAvailable(): bool;

    /**
     * Get supported market pairs
     * 
     * @return Collection<MarketPair>
     */
    public function getSupportedPairs(): Collection;

    /**
     * Get ticker data for a market pair
     */
    public function getTicker(string $baseCurrency, string $quoteCurrency): ExternalTicker;

    /**
     * Get order book for a market pair
     */
    public function getOrderBook(string $baseCurrency, string $quoteCurrency, int $depth = 20): ExternalOrderBook;

    /**
     * Get recent trades for a market pair
     * 
     * @return Collection<ExternalTrade>
     */
    public function getRecentTrades(string $baseCurrency, string $quoteCurrency, int $limit = 100): Collection;

    /**
     * Place a buy order
     * 
     * @return array Order details
     */
    public function placeBuyOrder(string $baseCurrency, string $quoteCurrency, string $amount, ?string $price = null): array;

    /**
     * Place a sell order
     * 
     * @return array Order details
     */
    public function placeSellOrder(string $baseCurrency, string $quoteCurrency, string $amount, ?string $price = null): array;

    /**
     * Cancel an order
     */
    public function cancelOrder(string $orderId): bool;

    /**
     * Get order status
     * 
     * @return array Order details
     */
    public function getOrderStatus(string $orderId): array;

    /**
     * Get account balance
     * 
     * @return array Balance by currency
     */
    public function getBalance(): array;

    /**
     * Get trading fees
     * 
     * @return array Fee structure
     */
    public function getFees(): array;

    /**
     * Test connectivity
     */
    public function ping(): bool;
}