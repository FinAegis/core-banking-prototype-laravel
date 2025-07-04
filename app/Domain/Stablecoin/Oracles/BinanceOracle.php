<?php

namespace App\Domain\Stablecoin\Oracles;

use App\Domain\Stablecoin\Contracts\OracleConnector;
use App\Domain\Stablecoin\ValueObjects\PriceData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceOracle implements OracleConnector
{
    private string $baseUrl = 'https://api.binance.com/api/v3';
    private array $symbolMap;
    
    public function __construct()
    {
        // Map our internal symbols to Binance symbols
        $this->symbolMap = [
            'BTC/USDT' => 'BTCUSDT',
            'ETH/USDT' => 'ETHUSDT',
            'BTC/USD' => 'BTCUSDC',
            'ETH/USD' => 'ETHUSDC',
            'EUR/USD' => 'EURUSDT',
            'GBP/USD' => 'GBPUSDT',
        ];
    }
    
    public function getPrice(string $base, string $quote): PriceData
    {
        $pair = "{$base}/{$quote}";
        $symbol = $this->symbolMap[$pair] ?? $this->constructSymbol($base, $quote);
        
        try {
            // In production, this would make actual API calls
            // For development, we simulate the response
            $response = $this->simulateBinanceResponse($symbol);
            
            return new PriceData(
                base: $base,
                quote: $quote,
                price: $response['price'],
                source: 'binance',
                timestamp: Carbon::createFromTimestampMs($response['closeTime']),
                volume24h: $response['volume'],
                changePercent24h: $response['priceChangePercent'],
                metadata: [
                    'symbol' => $symbol,
                    'quote_volume' => $response['quoteVolume'],
                    'count' => $response['count']
                ]
            );
        } catch (\Exception $e) {
            Log::error("Binance oracle error: {$e->getMessage()}");
            throw $e;
        }
    }
    
    public function getMultiplePrices(array $pairs): array
    {
        $prices = [];
        
        // Binance supports batch ticker requests
        // In production, we'd use /ticker/24hr endpoint with symbols parameter
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
        $symbol = $this->symbolMap["{$base}/{$quote}"] ?? $this->constructSymbol($base, $quote);
        
        try {
            // Binance provides kline/candlestick data for historical prices
            // In production, use /klines endpoint
            $response = $this->simulateHistoricalResponse($symbol, $timestamp);
            
            return new PriceData(
                base: $base,
                quote: $quote,
                price: $response['close'],
                source: 'binance',
                timestamp: $timestamp,
                volume24h: $response['volume'],
                changePercent24h: null,
                metadata: [
                    'symbol' => $symbol,
                    'open' => $response['open'],
                    'high' => $response['high'],
                    'low' => $response['low']
                ]
            );
        } catch (\Exception $e) {
            Log::error("Binance historical oracle error: {$e->getMessage()}");
            throw $e;
        }
    }
    
    public function isHealthy(): bool
    {
        try {
            // In production, check /ping endpoint
            $response = Http::timeout(5)->get("{$this->baseUrl}/ping");
            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function getSourceName(): string
    {
        return 'binance';
    }
    
    public function getPriority(): int
    {
        return 2; // Secondary priority after Chainlink
    }
    
    private function constructSymbol(string $base, string $quote): string
    {
        // Convert USDT/USDC to USD for our purposes
        if ($quote === 'USD') {
            $quote = 'USDT';
        }
        return strtoupper($base . $quote);
    }
    
    /**
     * Simulate Binance 24hr ticker response
     */
    private function simulateBinanceResponse(string $symbol): array
    {
        $basePrices = [
            'BTCUSDT' => 48000,
            'ETHUSDT' => 3200,
            'EURUSDT' => 1.09,
            'GBPUSDT' => 1.27,
        ];
        
        $basePrice = $basePrices[$symbol] ?? 1.0;
        $price = $basePrice + ($basePrice * (rand(-100, 100) / 10000));
        
        return [
            'symbol' => $symbol,
            'price' => number_format($price, 8, '.', ''),
            'priceChangePercent' => number_format(rand(-500, 500) / 100, 2, '.', ''),
            'volume' => number_format(rand(1000, 100000), 8, '.', ''),
            'quoteVolume' => number_format($price * rand(1000, 100000), 8, '.', ''),
            'count' => rand(10000, 500000),
            'closeTime' => round(microtime(true) * 1000),
        ];
    }
    
    /**
     * Simulate historical kline data
     */
    private function simulateHistoricalResponse(string $symbol, Carbon $timestamp): array
    {
        $basePrices = [
            'BTCUSDT' => 48000,
            'ETHUSDT' => 3200,
            'EURUSDT' => 1.09,
            'GBPUSDT' => 1.27,
        ];
        
        $basePrice = $basePrices[$symbol] ?? 1.0;
        $open = $basePrice + ($basePrice * (rand(-200, 200) / 10000));
        $close = $basePrice + ($basePrice * (rand(-200, 200) / 10000));
        $high = max($open, $close) + ($basePrice * (rand(0, 100) / 10000));
        $low = min($open, $close) - ($basePrice * (rand(0, 100) / 10000));
        
        return [
            'open' => number_format($open, 8, '.', ''),
            'high' => number_format($high, 8, '.', ''),
            'low' => number_format($low, 8, '.', ''),
            'close' => number_format($close, 8, '.', ''),
            'volume' => number_format(rand(1000, 100000), 8, '.', ''),
        ];
    }
}