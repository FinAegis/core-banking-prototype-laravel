<?php

namespace App\Domain\Stablecoin\Contracts;

use App\Domain\Stablecoin\ValueObjects\PriceData;
use Carbon\Carbon;

interface OracleConnector
{
    /**
     * Get current price for a trading pair
     */
    public function getPrice(string $base, string $quote): PriceData;
    
    /**
     * Get multiple prices in a single request
     */
    public function getMultiplePrices(array $pairs): array;
    
    /**
     * Get historical price at a specific timestamp
     */
    public function getHistoricalPrice(string $base, string $quote, Carbon $timestamp): PriceData;
    
    /**
     * Check if the oracle is healthy and responding
     */
    public function isHealthy(): bool;
    
    /**
     * Get the oracle source name
     */
    public function getSourceName(): string;
    
    /**
     * Get the oracle priority level (lower is higher priority)
     */
    public function getPriority(): int;
}