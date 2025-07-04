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

class KrakenConnector implements IExternalExchangeConnector
{
    private const BASE_URL = 'https://api.kraken.com';
    
    private string $apiKey;
    private string $apiSecret;
    private array $assetMap;

    public function __construct(string $apiKey = '', string $apiSecret = '')
    {
        $this->apiKey = $apiKey ?: config('services.kraken.api_key', '');
        $this->apiSecret = $apiSecret ?: config('services.kraken.api_secret', '');
        
        // Kraken uses different asset codes
        $this->assetMap = [
            'BTC' => 'XBT',
            'XBT' => 'BTC',
        ];
    }

    public function getName(): string
    {
        return 'Kraken';
    }

    public function isAvailable(): bool
    {
        try {
            return $this->ping();
        } catch (\Exception $e) {
            Log::warning('Kraken connectivity check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getSupportedPairs(): Collection
    {
        $cacheKey = 'kraken:tradable_asset_pairs';
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = Http::get($this->getPublicUrl('AssetPairs'));
            
            if (!$response->successful()) {
                throw new ExternalExchangeException('Failed to get asset pairs from Kraken');
            }
            
            $data = $response->json();
            
            if (isset($data['error']) && !empty($data['error'])) {
                throw new ExternalExchangeException('Kraken API error: ' . implode(', ', $data['error']));
            }
            
            return collect($data['result'])
                ->filter(fn($pair) => $pair['status'] === 'online')
                ->map(fn($pair, $pairName) => new MarketPair(
                    baseCurrency: $this->normalizeAsset($pair['base']),
                    quoteCurrency: $this->normalizeAsset($pair['quote']),
                    minOrderSize: BigDecimal::of($pair['ordermin'] ?? '0.0001'),
                    maxOrderSize: BigDecimal::of('1000000'), // Kraken doesn't provide max
                    tickSize: BigDecimal::of(pow(10, -$pair['pair_decimals'])),
                    pricePrecision: $pair['pair_decimals'],
                    amountPrecision: $pair['lot_decimals'],
                    isActive: true,
                    metadata: [
                        'pair' => $pairName,
                        'altname' => $pair['altname'],
                        'fee_volume_currency' => $pair['fee_volume_currency'] ?? null
                    ]
                ));
        });
    }

    public function getTicker(string $baseCurrency, string $quoteCurrency): ExternalTicker
    {
        $pair = $this->formatPair($baseCurrency, $quoteCurrency);
        $response = Http::get($this->getPublicUrl('Ticker'), ['pair' => $pair]);
        
        if (!$response->successful()) {
            throw new ExternalExchangeException("Failed to get ticker for $pair from Kraken");
        }
        
        $data = $response->json();
        
        if (isset($data['error']) && !empty($data['error'])) {
            throw new ExternalExchangeException('Kraken API error: ' . implode(', ', $data['error']));
        }
        
        $tickerData = array_values($data['result'])[0];
        
        return new ExternalTicker(
            baseCurrency: $baseCurrency,
            quoteCurrency: $quoteCurrency,
            bid: BigDecimal::of($tickerData['b'][0]),
            ask: BigDecimal::of($tickerData['a'][0]),
            last: BigDecimal::of($tickerData['c'][0]),
            volume24h: BigDecimal::of($tickerData['v'][1]), // 24h volume
            high24h: BigDecimal::of($tickerData['h'][1]), // 24h high
            low24h: BigDecimal::of($tickerData['l'][1]), // 24h low
            change24h: BigDecimal::of($tickerData['c'][0])
                ->minus($tickerData['o'])
                ->dividedBy($tickerData['o'], 18)
                ->multipliedBy(100),
            timestamp: new \DateTimeImmutable(),
            exchange: $this->getName(),
            metadata: [
                'pair' => $pair,
                'trades_24h' => $tickerData['t'][1] // 24h trade count
            ]
        );
    }

    public function getOrderBook(string $baseCurrency, string $quoteCurrency, int $depth = 20): ExternalOrderBook
    {
        $pair = $this->formatPair($baseCurrency, $quoteCurrency);
        $response = Http::get($this->getPublicUrl('Depth'), [
            'pair' => $pair,
            'count' => $depth
        ]);
        
        if (!$response->successful()) {
            throw new ExternalExchangeException("Failed to get order book for $pair from Kraken");
        }
        
        $data = $response->json();
        
        if (isset($data['error']) && !empty($data['error'])) {
            throw new ExternalExchangeException('Kraken API error: ' . implode(', ', $data['error']));
        }
        
        $bookData = array_values($data['result'])[0];
        
        return new ExternalOrderBook(
            baseCurrency: $baseCurrency,
            quoteCurrency: $quoteCurrency,
            bids: collect($bookData['bids'])->map(fn($bid) => [
                'price' => BigDecimal::of($bid[0]),
                'amount' => BigDecimal::of($bid[1])
            ]),
            asks: collect($bookData['asks'])->map(fn($ask) => [
                'price' => BigDecimal::of($ask[0]),
                'amount' => BigDecimal::of($ask[1])
            ]),
            timestamp: new \DateTimeImmutable('@' . $bid[2]),
            exchange: $this->getName(),
            metadata: ['pair' => $pair]
        );
    }

    public function getRecentTrades(string $baseCurrency, string $quoteCurrency, int $limit = 100): Collection
    {
        $pair = $this->formatPair($baseCurrency, $quoteCurrency);
        $response = Http::get($this->getPublicUrl('Trades'), ['pair' => $pair]);
        
        if (!$response->successful()) {
            throw new ExternalExchangeException("Failed to get recent trades for $pair from Kraken");
        }
        
        $data = $response->json();
        
        if (isset($data['error']) && !empty($data['error'])) {
            throw new ExternalExchangeException('Kraken API error: ' . implode(', ', $data['error']));
        }
        
        $tradesData = array_values($data['result'])[0];
        
        return collect($tradesData)
            ->take($limit)
            ->map(fn($trade, $index) => new ExternalTrade(
                tradeId: (string) $index,
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                price: BigDecimal::of($trade[0]),
                amount: BigDecimal::of($trade[1]),
                side: $trade[3] === 'b' ? 'buy' : 'sell',
                timestamp: new \DateTimeImmutable('@' . $trade[2]),
                exchange: $this->getName(),
                metadata: [
                    'pair' => $pair,
                    'order_type' => $trade[4] === 'm' ? 'market' : 'limit'
                ]
            ));
    }

    public function placeBuyOrder(string $baseCurrency, string $quoteCurrency, string $amount, ?string $price = null): array
    {
        return $this->placeOrder($baseCurrency, $quoteCurrency, 'buy', $amount, $price);
    }

    public function placeSellOrder(string $baseCurrency, string $quoteCurrency, string $amount, ?string $price = null): array
    {
        return $this->placeOrder($baseCurrency, $quoteCurrency, 'sell', $amount, $price);
    }

    private function placeOrder(string $baseCurrency, string $quoteCurrency, string $type, string $amount, ?string $price): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new ExternalExchangeException('API credentials not configured for Kraken');
        }
        
        $pair = $this->formatPair($baseCurrency, $quoteCurrency);
        
        $params = [
            'pair' => $pair,
            'type' => $type,
            'ordertype' => $price ? 'limit' : 'market',
            'volume' => $amount,
        ];
        
        if ($price) {
            $params['price'] = $price;
        }
        
        $response = $this->privateRequest('AddOrder', $params);
        
        if (isset($response['error']) && !empty($response['error'])) {
            throw new ExternalExchangeException('Failed to place order on Kraken: ' . implode(', ', $response['error']));
        }
        
        return $response['result'];
    }

    public function cancelOrder(string $orderId): bool
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new ExternalExchangeException('API credentials not configured for Kraken');
        }
        
        $response = $this->privateRequest('CancelOrder', ['txid' => $orderId]);
        
        if (isset($response['error']) && !empty($response['error'])) {
            throw new ExternalExchangeException('Failed to cancel order on Kraken: ' . implode(', ', $response['error']));
        }
        
        return isset($response['result']['count']) && $response['result']['count'] > 0;
    }

    public function getOrderStatus(string $orderId): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new ExternalExchangeException('API credentials not configured for Kraken');
        }
        
        $response = $this->privateRequest('QueryOrders', ['txid' => $orderId]);
        
        if (isset($response['error']) && !empty($response['error'])) {
            throw new ExternalExchangeException('Failed to get order status from Kraken: ' . implode(', ', $response['error']));
        }
        
        return $response['result'];
    }

    public function getBalance(): array
    {
        if (empty($this->apiKey) || empty($this->apiSecret)) {
            throw new ExternalExchangeException('API credentials not configured for Kraken');
        }
        
        $response = $this->privateRequest('Balance');
        
        if (isset($response['error']) && !empty($response['error'])) {
            throw new ExternalExchangeException('Failed to get balance from Kraken: ' . implode(', ', $response['error']));
        }
        
        return collect($response['result'])
            ->mapWithKeys(fn($balance, $asset) => [
                $this->normalizeAsset($asset) => [
                    'free' => $balance,
                    'locked' => '0', // Kraken doesn't separate locked balance
                    'total' => $balance
                ]
            ])
            ->toArray();
    }

    public function getFees(): array
    {
        return [
            'maker' => '0.0016', // 0.16%
            'taker' => '0.0026', // 0.26%
            'volume_discount' => true
        ];
    }

    public function ping(): bool
    {
        $response = Http::get($this->getPublicUrl('SystemStatus'));
        
        if (!$response->successful()) {
            return false;
        }
        
        $data = $response->json();
        return isset($data['result']['status']) && $data['result']['status'] === 'online';
    }

    private function formatPair(string $baseCurrency, string $quoteCurrency): string
    {
        $base = $this->getKrakenAsset($baseCurrency);
        $quote = $this->getKrakenAsset($quoteCurrency);
        return $base . $quote;
    }

    private function getKrakenAsset(string $asset): string
    {
        // Convert to Kraken's asset naming
        if ($asset === 'BTC') return 'XBT';
        if (strlen($asset) === 3) return 'X' . $asset;
        return $asset;
    }

    private function normalizeAsset(string $krakenAsset): string
    {
        // Convert from Kraken's asset naming
        if ($krakenAsset === 'XBT' || $krakenAsset === 'XXBT') return 'BTC';
        if (str_starts_with($krakenAsset, 'X') && strlen($krakenAsset) === 4) {
            return substr($krakenAsset, 1);
        }
        if (str_starts_with($krakenAsset, 'Z') && strlen($krakenAsset) === 4) {
            return substr($krakenAsset, 1);
        }
        return $krakenAsset;
    }

    private function getPublicUrl(string $method): string
    {
        return self::BASE_URL . '/0/public/' . $method;
    }

    private function getPrivateUrl(string $method): string
    {
        return self::BASE_URL . '/0/private/' . $method;
    }

    private function privateRequest(string $method, array $params = []): array
    {
        $url = $this->getPrivateUrl($method);
        $nonce = time() * 1000;
        
        $params['nonce'] = $nonce;
        $postData = http_build_query($params);
        
        $path = '/0/private/' . $method;
        $sign = base64_encode(hash_hmac(
            'sha512',
            $path . hash('sha256', $nonce . $postData, true),
            base64_decode($this->apiSecret),
            true
        ));
        
        $response = Http::withHeaders([
            'API-Key' => $this->apiKey,
            'API-Sign' => $sign,
        ])->asForm()->post($url, $params);
        
        if (!$response->successful()) {
            throw new ExternalExchangeException('Kraken API request failed');
        }
        
        return $response->json();
    }
}