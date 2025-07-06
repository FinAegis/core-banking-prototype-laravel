<?php

namespace App\Domain\Exchange\Contracts;

interface ExternalExchangeServiceInterface
{
    /**
     * Connect to an external exchange
     */
    public function connect(string $exchange, array $credentials): bool;
    
    /**
     * Disconnect from an external exchange
     */
    public function disconnect(string $exchange): bool;
    
    /**
     * Get market data from external exchange
     */
    public function getMarketData(string $exchange, string $pair): array;
    
    /**
     * Execute arbitrage opportunity
     */
    public function executeArbitrage(array $opportunity): array;
    
    /**
     * Get price alignment data
     */
    public function getPriceAlignment(): array;
    
    /**
     * Update price alignment settings
     */
    public function updatePriceAlignment(array $settings): bool;
}