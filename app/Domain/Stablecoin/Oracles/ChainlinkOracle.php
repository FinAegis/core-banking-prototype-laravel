<?php

namespace App\Domain\Stablecoin\Oracles;

use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\ValueObjects\PriceData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChainlinkOracle implements OracleConnector
{
    private string $apiKey;
    private string $baseUrl;
    private array $priceFeedMap;
    
    public function __construct()
    {
        $this->apiKey = config('stablecoin.oracles.chainlink.api_key', '');
        $this->baseUrl = config('stablecoin.oracles.chainlink.base_url', 'https://api.chain.link/v1');
        
        // Map of trading pairs to Chainlink price feed addresses
        $this->priceFeedMap = [
            'BTC/USD' => '0xF4030086522a5bEEa4988F8cA5B36dbC97BeE88c',
            'ETH/USD' => '0x5f4eC3Df9cbd43714FE2740f5E3616155c5b8419',
            'EUR/USD' => '0xb49f677943BC038e9857d61E7d053CaA2C1734C1',
            'GBP/USD' => '0x5c0Ab2d9b5a7ed9f470386e82BB36A3613cDd4b5',
            // Add more price feeds as needed
        ];
    }
    
    public function getPrice(string $base, string $quote): PriceData
    {
        $pair = "{$base}/{$quote}";
        
        if (!isset($this->priceFeedMap[$pair])) {
            throw new \InvalidArgumentException("Price feed not available for {$pair}");
        }
        
        try {
            // In production, this would connect to actual Chainlink nodes
            // For now, we'll simulate with realistic data
            $response = $this->simulateChainlinkResponse($base, $quote);
            
            return new PriceData(
                base: $base,
                quote: $quote,
                price: $response['price'],
                source: 'chainlink',
                timestamp: Carbon::createFromTimestamp($response['timestamp']),
                volume24h: null, // Chainlink doesn't provide volume
                changePercent24h: null,
                metadata: [
                    'feed_address' => $this->priceFeedMap[$pair],
                    'round_id' => $response['roundId'],
                    'updated_at' => $response['updatedAt']
                ]
            );
        } catch (\Exception $e) {
            Log::error("Chainlink oracle error: {$e->getMessage()}");
            throw $e;
        }
    }
    
    public function getMultiplePrices(array $pairs): array
    {
        $prices = [];
        
        foreach ($pairs as $pair) {
            try {
                [$base, $quote] = explode('/', $pair);
                $prices[$pair] = $this->getPrice($base, $quote);
            } catch (\Exception $e) {
                Log::warning("Failed to get price for {$pair}: {$e->getMessage()}");
            }
        }
        
        return $prices;
    }
    
    public function getHistoricalPrice(string $base, string $quote, Carbon $timestamp): PriceData
    {
        // Chainlink doesn't provide historical data via API
        // In production, this would query on-chain historical rounds
        throw new \RuntimeException("Historical prices not available from Chainlink oracle");
    }
    
    public function isHealthy(): bool
    {
        try {
            // Check if we can get a common price feed
            $this->getPrice('ETH', 'USD');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getSourceName(): string
    {
        return 'chainlink';
    }
    
    public function getPriority(): int
    {
        return 1; // Highest priority
    }
    
    /**
     * Simulate Chainlink response for development
     */
    private function simulateChainlinkResponse(string $base, string $quote): array
    {
        $basePrices = [
            'BTC' => 48000 + rand(-1000, 1000),
            'ETH' => 3200 + rand(-100, 100),
            'EUR' => 1.09 + rand(-5, 5) / 100,
            'GBP' => 1.27 + rand(-5, 5) / 100,
        ];
        
        $price = $basePrices[$base] ?? 1.0;
        
        if ($quote !== 'USD') {
            $quoteInUsd = $basePrices[$quote] ?? 1.0;
            $price = $price / $quoteInUsd;
        }
        
        return [
            'price' => number_format($price, 8, '.', ''),
            'timestamp' => time(),
            'roundId' => rand(1000000, 9999999),
            'updatedAt' => time() - rand(0, 300),
        ];
    }
}