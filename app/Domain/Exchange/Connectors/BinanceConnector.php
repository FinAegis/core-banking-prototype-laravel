<?php

namespace App\Domain\Exchange\Connectors;

use App\Domain\Exchange\Contracts\IExternalExchangeConnector;
use App\Domain\Exchange\Exceptions\ExternalExchangeException;
use App\Domain\Exchange\ValueObjects\ExternalOrderBook;
use App\Domain\Exchange\ValueObjects\ExternalTicker;
use App\Domain\Exchange\ValueObjects\ExternalTrade;
use App\Domain\Exchange\ValueObjects\MarketPair;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceConnector implements IExternalExchangeConnector
{
    private const BASE_URL = 'https://api.binance.com';
    private const BASE_URL_US = 'https://api.binance.us';
    
    private string $apiKey;
    private string $apiSecret;
    private string $baseUrl;
    private bool $isTestnet;

    public function __construct(
        string $apiKey = '',
        string $apiSecret = '',
        bool $isUS = false,
        bool $isTestnet = false
    ) {
        $this->apiKey = $apiKey ?: (string) config('services.binance.api_key', '');
        $this->apiSecret = $apiSecret ?: (string) config('services.binance.api_secret', '');
        $this->baseUrl = $isUS ? self::BASE_URL_US : self::BASE_URL;
        $this->isTestnet = $isTestnet;
        
        if ($isTestnet) {
            $this->baseUrl = 'https://testnet.binance.vision';
        }
    }

    public function getName(): string
    {
        return 'Binance' . ($this->isTestnet ? ' Testnet' : '');
    }

    public function isAvailable(): bool
    {
        try {
            return $this->ping();
        } catch (\Exception $e) {
            Log::warning('Binance connectivity check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getSupportedPairs(): Collection
    {
        $cacheKey = 'binance:exchange_info:' . md5($this->baseUrl);
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = Http::get($this->baseUrl . '/api/v3/exchangeInfo');
            
            if (!$response->successful()) {
                throw new ExternalExchangeException('Failed to get exchange info from Binance');
            }
            
            $data = $response->json();
            
            return collect($data['symbols'])
                ->filter(fn($symbol) => $symbol['status'] === 'TRADING')
                ->map(fn($symbol) => new MarketPair(
                    baseCurrency: $symbol['baseAsset'],
                    quoteCurrency: $symbol['quoteAsset'],
                    minOrderSize: BigDecimal::of($symbol['filters'][1]['minQty'] ?? '0.00001'),
                    maxOrderSize: BigDecimal::of($symbol['filters'][1]['maxQty'] ?? '9000000'),
                    tickSize: BigDecimal::of($symbol['filters'][0]['tickSize'] ?? '0.00001'),
                    pricePrecision: $symbol['quotePrecision'],
                    amountPrecision: $symbol['baseAssetPrecision'],
                    isActive: true,
                    metadata: [
                        'symbol' => $symbol['symbol'],
                        'permissions' => $symbol['permissions'] ?? []
                    ]
                ));
        });
    }

    public function getTicker(string $baseCurrency, string $quoteCurrency): ExternalTicker
    {
        $symbol = $this->formatSymbol($baseCurrency, $quoteCurrency);
        $response = Http::get($this->baseUrl . '/api/v3/ticker/24hr', ['symbol' => $symbol]);
        
        if (!$response->successful()) {
            throw new ExternalExchangeException("Failed to get ticker for $symbol from Binance");
        }
        
        $data = $response->json();
        
        return new ExternalTicker(
            baseCurrency: $baseCurrency,
            quoteCurrency: $quoteCurrency,
            bid: BigDecimal::of($data['bidPrice']),
            ask: BigDecimal::of($data['askPrice']),
            last: BigDecimal::of($data['lastPrice']),
            volume24h: BigDecimal::of($data['volume']),
            high24h: BigDecimal::of($data['highPrice']),
            low24h: BigDecimal::of($data['lowPrice']),
            change24h: BigDecimal::of($data['priceChangePercent']),
            timestamp: new \DateTimeImmutable('@' . intval($data['closeTime'] / 1000)),
            exchange: $this->getName(),
            metadata: [
                'symbol' => $symbol,
                'count' => $data['count'],
                'quote_volume' => $data['quoteVolume']
            ]
        );
    }

    public function getOrderBook(string $baseCurrency, string $quoteCurrency, int $depth = 20): ExternalOrderBook
    {
        $symbol = $this->formatSymbol($baseCurrency, $quoteCurrency);
        $response = Http::get($this->baseUrl . '/api/v3/depth', [
            'symbol' => $symbol,
            'limit' => min($depth, 100)
        ]);
        
        if (!$response->successful()) {
            throw new ExternalExchangeException("Failed to get order book for $symbol from Binance");
        }
        
        $data = $response->json();
        
        return new ExternalOrderBook(
            baseCurrency: $baseCurrency,
            quoteCurrency: $quoteCurrency,
            bids: collect($data['bids'])->map(fn($bid) => [
                'price' => BigDecimal::of($bid[0]),
                'amount' => BigDecimal::of($bid[1])
            ]),
            asks: collect($data['asks'])->map(fn($ask) => [
                'price' => BigDecimal::of($ask[0]),
                'amount' => BigDecimal::of($ask[1])
            ]),
            timestamp: new \DateTimeImmutable(),
            exchange: $this->getName(),
            metadata: [
                'symbol' => $symbol,
                'last_update_id' => $data['lastUpdateId']
            ]
        );
    }

    public function getRecentTrades(string $baseCurrency, string $quoteCurrency, int $limit = 100): Collection
    {
        $symbol = $this->formatSymbol($baseCurrency, $quoteCurrency);
        $response = Http::get($this->baseUrl . '/api/v3/trades', [
            'symbol' => $symbol,
            'limit' => min($limit, 1000)
        ]);
        
        if (!$response->successful()) {
            throw new ExternalExchangeException("Failed to get recent trades for $symbol from Binance");
        }
        
        $data = $response->json();
        
        return collect($data)->map(fn($trade) => new ExternalTrade(
            tradeId: (string) $trade['id'],
            baseCurrency: $baseCurrency,
            quoteCurrency: $quoteCurrency,
            price: BigDecimal::of($trade['price']),
            amount: BigDecimal::of($trade['qty']),
            side: $trade['isBuyerMaker'] ? 'sell' : 'buy',
            timestamp: new \DateTimeImmutable('@' . intval($trade['time'] / 1000)),
            exchange: $this->getName(),
            metadata: [
                'symbol' => $symbol,
                'is_best_match' => $trade['isBestMatch']
            ]
        ));
    }

    public function placeBuyOrder(string $baseCurrency, string $quoteCurrency, string $amount, ?string $price = null): array
    {
        return $this->placeOrder($baseCurrency, $quoteCurrency, 'BUY', $amount, $price);
    }

    public function placeSellOrder(string $baseCurrency, string $quoteCurrency, string $amount, ?string $price = null): array
    {
        return $this->placeOrder($baseCurrency, $quoteCurrency, 'SELL', $amount, $price);
    }

    private function placeOrder(string $baseCurrency, string $quoteCurrency, string $side, string $amount, ?string $price): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new ExternalExchangeException('API credentials not configured for Binance');
        }
        
        $symbol = $this->formatSymbol($baseCurrency, $quoteCurrency);
        $params = [
            'symbol' => $symbol,
            'side' => $side,
            'type' => $price ? 'LIMIT' : 'MARKET',
            'quantity' => $amount,
            'timestamp' => time() * 1000,
        ];
        
        if ($price) {
            $params['price'] = $price;
            $params['timeInForce'] = 'GTC';
        }
        
        $signature = $this->generateSignature($params);
        $params['signature'] = $signature;
        
        $response = Http::withHeaders([
            'X-MBX-APIKEY' => $this->apiKey
        ])->post($this->baseUrl . '/api/v3/order', $params);
        
        if (!$response->successful()) {
            throw new ExternalExchangeException('Failed to place order on Binance: ' . $response->body());
        }
        
        return $response->json();
    }

    public function cancelOrder(string $orderId): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new ExternalExchangeException('API credentials not configured for Binance');
        }
        
        // Note: Binance requires symbol for order cancellation
        // In production, we'd need to track the symbol with the order
        throw new ExternalExchangeException('Order cancellation requires symbol tracking');
    }

    public function getOrderStatus(string $orderId): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new ExternalExchangeException('API credentials not configured for Binance');
        }
        
        // Note: Binance requires symbol for order status
        // In production, we'd need to track the symbol with the order
        throw new ExternalExchangeException('Order status requires symbol tracking');
    }

    public function getBalance(): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new ExternalExchangeException('API credentials not configured for Binance');
        }
        
        $params = [
            'timestamp' => time() * 1000,
        ];
        
        $signature = $this->generateSignature($params);
        $params['signature'] = $signature;
        
        $response = Http::withHeaders([
            'X-MBX-APIKEY' => $this->apiKey
        ])->get($this->baseUrl . '/api/v3/account', $params);
        
        if (!$response->successful()) {
            throw new ExternalExchangeException('Failed to get balance from Binance');
        }
        
        $data = $response->json();
        
        return collect($data['balances'])
            ->filter(fn($balance) => BigDecimal::of($balance['free'])->plus($balance['locked'])->isGreaterThan(0))
            ->mapWithKeys(fn($balance) => [
                $balance['asset'] => [
                    'free' => $balance['free'],
                    'locked' => $balance['locked'],
                    'total' => BigDecimal::of($balance['free'])->plus($balance['locked'])->__toString()
                ]
            ])
            ->toArray();
    }

    public function getFees(): array
    {
        return [
            'maker' => '0.001', // 0.1%
            'taker' => '0.001', // 0.1%
            'discount' => [
                'BNB' => '0.25' // 25% discount when paying fees in BNB
            ]
        ];
    }

    public function ping(): bool
    {
        $response = Http::get($this->baseUrl . '/api/v3/ping');
        return $response->successful();
    }

    private function formatSymbol(string $baseCurrency, string $quoteCurrency): string
    {
        // Binance uses concatenated symbols without separators
        return strtoupper($baseCurrency . $quoteCurrency);
    }

    private function generateSignature(array $params): string
    {
        $query = http_build_query($params);
        return hash_hmac('sha256', $query, $this->apiSecret);
    }
}