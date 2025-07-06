<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Contracts\PriceAggregatorInterface;

class PriceAggregator implements PriceAggregatorInterface
{
    public function getAggregatedPrice(string $symbol): array
    {
        return [
            'symbol' => $symbol,
            'average' => 0,
            'min' => 0,
            'max' => 0,
            'exchanges' => [],
        ];
    }
    
    public function getBestBid(string $symbol): array
    {
        return [
            'exchange' => null,
            'price' => 0,
            'amount' => 0,
        ];
    }
    
    public function getBestAsk(string $symbol): array
    {
        return [
            'exchange' => null,
            'price' => 0,
            'amount' => 0,
        ];
    }
    
    /**
     * Get prices across all exchanges
     */
    public function getPricesAcrossExchanges(string $pair): array
    {
        return [];
    }
}