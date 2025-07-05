<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Aggregates\OrderAggregate;
use App\Domain\Exchange\Contracts\ExternalLiquidityServiceInterface;
use App\Domain\Exchange\Models\ExchangeRate;
use App\Domain\Exchange\Models\Order;
use App\Domain\Exchange\Models\OrderBook;
use App\Domain\Exchange\Models\Trade;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExternalLiquidityService implements ExternalLiquidityServiceInterface
{
    public function __construct(
        private ExternalExchangeConnectorRegistry $connectorRegistry,
        private ExchangeService $exchangeService
    ) {}

    /**
     * Find arbitrage opportunities between internal and external exchanges
     */
    public function findArbitrageOpportunities(string $baseCurrency, string $quoteCurrency): array
    {
        $opportunities = [];

        // Get internal order book
        $internalOrderBook = $this->exchangeService->getOrderBook($baseCurrency, $quoteCurrency);
        
        if (empty($internalOrderBook['buy_orders']) || empty($internalOrderBook['sell_orders'])) {
            return $opportunities;
        }

        $internalBestBid = BigDecimal::of($internalOrderBook['buy_orders'][0]['price']);
        $internalBestAsk = BigDecimal::of($internalOrderBook['sell_orders'][0]['price']);

        // Get external prices from all connected exchanges
        $externalBids = $this->connectorRegistry->getAllBids($baseCurrency, $quoteCurrency);
        $externalAsks = $this->connectorRegistry->getAllAsks($baseCurrency, $quoteCurrency);

        // Check for arbitrage opportunities
        foreach ($externalAsks as $externalAsk) {
            $askPrice = BigDecimal::of($externalAsk['price']);
            
            // Buy external, sell internal
            if ($askPrice->isLessThan($internalBestBid)) {
                $profitPercentage = $internalBestBid->minus($askPrice)
                    ->dividedBy($askPrice, 6, RoundingMode::HALF_UP)
                    ->multipliedBy('100');
                
                $opportunities[] = [
                    'type' => 'buy_external_sell_internal',
                    'external_exchange' => $externalAsk['exchange'],
                    'external_price' => $askPrice->__toString(),
                    'internal_price' => $internalBestBid->__toString(),
                    'profit_percentage' => $profitPercentage->__toString(),
                    'action' => 'Buy on ' . $externalAsk['exchange'] . ' at ' . $askPrice . ', sell internally at ' . $internalBestBid,
                ];
            }
        }

        foreach ($externalBids as $externalBid) {
            $bidPrice = BigDecimal::of($externalBid['price']);
            
            // Buy internal, sell external
            if ($bidPrice->isGreaterThan($internalBestAsk)) {
                $profitPercentage = $bidPrice->minus($internalBestAsk)
                    ->dividedBy($internalBestAsk, 6, RoundingMode::HALF_UP)
                    ->multipliedBy('100');
                
                $opportunities[] = [
                    'type' => 'buy_internal_sell_external',
                    'external_exchange' => $externalBid['exchange'],
                    'external_price' => $bidPrice->__toString(),
                    'internal_price' => $internalBestAsk->__toString(),
                    'profit_percentage' => $profitPercentage->__toString(),
                    'action' => 'Buy internally at ' . $internalBestAsk . ', sell on ' . $externalBid['exchange'] . ' at ' . $bidPrice,
                ];
            }
        }

        return $opportunities;
    }

    /**
     * Provide liquidity from external exchanges when internal liquidity is low
     */
    public function provideLiquidity(string $baseCurrency, string $quoteCurrency, string $side, string $amount): array
    {
        $result = [
            'success' => false,
            'orders_placed' => 0,
            'total_amount' => '0',
            'message' => ''
        ];

        // Get system account ID from config or use a default
        $systemAccountId = config('exchange.system_account_id', 'system-liquidity-provider');

        // Get best prices from external exchanges
        if ($side === 'buy') {
            $bestExternal = $this->connectorRegistry->getBestAsk($baseCurrency, $quoteCurrency);
        } else {
            $bestExternal = $this->connectorRegistry->getBestBid($baseCurrency, $quoteCurrency);
        }

        if (!$bestExternal) {
            $result['message'] = 'No external liquidity available';
            return $result;
        }

        $placedOrders = [];
        $totalAmount = BigDecimal::of('0');

        DB::transaction(function () use (
            $baseCurrency,
            $quoteCurrency,
            $side,
            $amount,
            $systemAccountId,
            $bestExternal,
            &$placedOrders,
            &$totalAmount
        ) {
            $requestedAmount = BigDecimal::of($amount);
            $basePrice = BigDecimal::of($bestExternal['price']);
            
            // Split the requested amount into multiple orders for better liquidity distribution
            $orderCount = 5;
            $amountPerOrder = $requestedAmount->dividedBy($orderCount, 8, RoundingMode::FLOOR);
            
            for ($i = 0; $i < $orderCount; $i++) {
                // Adjust price based on side and order index
                if ($side === 'buy') {
                    // For buy liquidity, we place sell orders at slightly higher prices
                    $priceMultiplier = BigDecimal::of('1')->plus(BigDecimal::of('0.001')->multipliedBy($i));
                    $orderType = 'sell';
                } else {
                    // For sell liquidity, we place buy orders at slightly lower prices
                    $priceMultiplier = BigDecimal::of('1')->minus(BigDecimal::of('0.001')->multipliedBy($i));
                    $orderType = 'buy';
                }
                
                $orderPrice = $basePrice->multipliedBy($priceMultiplier);
                $orderId = Str::uuid()->toString();
                
                OrderAggregate::retrieve($orderId)
                    ->placeOrder(
                        accountId: $systemAccountId,
                        type: $orderType,
                        orderType: 'limit',
                        baseCurrency: $baseCurrency,
                        quoteCurrency: $quoteCurrency,
                        amount: $amountPerOrder->__toString(),
                        price: $orderPrice->__toString(),
                        metadata: [
                            'source' => 'external_liquidity',
                            'external_exchange' => $bestExternal['exchange'],
                            'external_price' => $basePrice->__toString(),
                            'liquidity_side' => $side
                        ]
                    )
                    ->persist();
                
                $placedOrders[] = [
                    'order_id' => $orderId,
                    'type' => $orderType,
                    'amount' => $amountPerOrder->__toString(),
                    'price' => $orderPrice->__toString(),
                    'exchange' => $bestExternal['exchange']
                ];
                
                $totalAmount = $totalAmount->plus($amountPerOrder);
            }
        });

        $result['success'] = true;
        $result['orders_placed'] = count($placedOrders);
        $result['total_amount'] = $totalAmount->__toString();
        $result['orders'] = $placedOrders;
        $result['message'] = "Successfully placed {$result['orders_placed']} liquidity orders";

        return $result;
    }

    /**
     * Align internal prices with external market prices
     */
    public function alignPrices(string $baseCurrency, string $quoteCurrency, float $maxDeviationPercentage = 1.0): array
    {
        $result = [
            'aligned' => false,
            'internal_best_bid' => null,
            'internal_best_ask' => null,
            'external_best_bid' => null,
            'external_best_ask' => null,
            'bid_deviation' => null,
            'ask_deviation' => null,
            'actions_taken' => []
        ];

        // Get internal prices
        $orderBook = $this->exchangeService->getOrderBook($baseCurrency, $quoteCurrency);
        
        if (empty($orderBook['buy_orders']) || empty($orderBook['sell_orders'])) {
            $result['actions_taken'][] = 'No internal orders to align';
            return $result;
        }

        $internalBestBid = BigDecimal::of($orderBook['buy_orders'][0]['price']);
        $internalBestAsk = BigDecimal::of($orderBook['sell_orders'][0]['price']);

        // Get external prices
        $externalBestBid = $this->connectorRegistry->getBestBid($baseCurrency, $quoteCurrency);
        $externalBestAsk = $this->connectorRegistry->getBestAsk($baseCurrency, $quoteCurrency);

        if (!$externalBestBid || !$externalBestAsk) {
            $result['actions_taken'][] = 'No external prices available';
            return $result;
        }

        $extBidPrice = BigDecimal::of($externalBestBid['price']);
        $extAskPrice = BigDecimal::of($externalBestAsk['price']);

        // Calculate deviations
        $bidDeviation = $internalBestBid->minus($extBidPrice)
            ->dividedBy($extBidPrice, 6, RoundingMode::HALF_UP)
            ->multipliedBy('100')
            ->abs();
        
        $askDeviation = $internalBestAsk->minus($extAskPrice)
            ->dividedBy($extAskPrice, 6, RoundingMode::HALF_UP)
            ->multipliedBy('100')
            ->abs();

        $result['internal_best_bid'] = $internalBestBid->__toString();
        $result['internal_best_ask'] = $internalBestAsk->__toString();
        $result['external_best_bid'] = $extBidPrice->__toString();
        $result['external_best_ask'] = $extAskPrice->__toString();
        $result['bid_deviation'] = $bidDeviation->__toString();
        $result['ask_deviation'] = $askDeviation->__toString();

        // Check if alignment is needed
        $maxDeviation = BigDecimal::of((string)$maxDeviationPercentage);
        
        if ($bidDeviation->isGreaterThan($maxDeviation) || $askDeviation->isGreaterThan($maxDeviation)) {
            // Place liquidity orders to align prices
            $systemAccountId = config('exchange.system_account_id', 'system-liquidity-provider');
            
            if ($bidDeviation->isGreaterThan($maxDeviation)) {
                // Place sell order at external bid price
                $orderId = Str::uuid()->toString();
                OrderAggregate::retrieve($orderId)
                    ->placeOrder(
                        accountId: $systemAccountId,
                        type: 'sell',
                        orderType: 'limit',
                        baseCurrency: $baseCurrency,
                        quoteCurrency: $quoteCurrency,
                        amount: '0.1',
                        price: $extBidPrice->__toString(),
                        metadata: [
                            'source' => 'price_alignment',
                            'reason' => 'bid_deviation',
                            'deviation' => $bidDeviation->__toString()
                        ]
                    )
                    ->persist();
                
                $result['actions_taken'][] = "Placed sell order at {$extBidPrice} to align bid";
            }
            
            if ($askDeviation->isGreaterThan($maxDeviation)) {
                // Place buy order at external ask price
                $orderId = Str::uuid()->toString();
                OrderAggregate::retrieve($orderId)
                    ->placeOrder(
                        accountId: $systemAccountId,
                        type: 'buy',
                        orderType: 'limit',
                        baseCurrency: $baseCurrency,
                        quoteCurrency: $quoteCurrency,
                        amount: '0.1',
                        price: $extAskPrice->__toString(),
                        metadata: [
                            'source' => 'price_alignment',
                            'reason' => 'ask_deviation',
                            'deviation' => $askDeviation->__toString()
                        ]
                    )
                    ->persist();
                
                $result['actions_taken'][] = "Placed buy order at {$extAskPrice} to align ask";
            }
            
            $result['aligned'] = true;
        } else {
            $result['actions_taken'][] = 'Prices are within acceptable deviation';
        }

        return $result;
    }

    /**
     * Execute arbitrage trade
     */
    public function executeArbitrage(array $opportunity): array
    {
        // This would integrate with external exchange APIs to execute the actual trades
        // For now, we'll return a placeholder response
        return [
            'executed' => false,
            'reason' => 'External exchange trading not yet implemented',
            'opportunity' => $opportunity
        ];
    }

    /**
     * Get liquidity depth from external sources
     */
    public function getExternalLiquidityDepth(string $baseCurrency, string $quoteCurrency): array
    {
        $depth = [
            'bids' => [],
            'asks' => [],
            'total_bid_volume' => '0',
            'total_ask_volume' => '0'
        ];

        // Aggregate order book data from all connected exchanges
        foreach ($this->connectorRegistry->getConnectors() as $connector) {
            try {
                $externalBook = $connector->getOrderBook($baseCurrency, $quoteCurrency);
                
                // Merge bids
                foreach ($externalBook['bids'] ?? [] as $bid) {
                    $depth['bids'][] = [
                        'exchange' => $connector->getName(),
                        'price' => $bid['price'],
                        'amount' => $bid['amount']
                    ];
                }
                
                // Merge asks
                foreach ($externalBook['asks'] ?? [] as $ask) {
                    $depth['asks'][] = [
                        'exchange' => $connector->getName(),
                        'price' => $ask['price'],
                        'amount' => $ask['amount']
                    ];
                }
            } catch (\Exception $e) {
                // Log error but continue with other exchanges
                \Log::error("Failed to get order book from {$connector->getName()}: {$e->getMessage()}");
            }
        }

        // Sort by price
        usort($depth['bids'], fn($a, $b) => BigDecimal::of($b['price'])->compareTo(BigDecimal::of($a['price'])));
        usort($depth['asks'], fn($a, $b) => BigDecimal::of($a['price'])->compareTo(BigDecimal::of($b['price'])));

        // Calculate total volumes
        $totalBidVolume = BigDecimal::of('0');
        $totalAskVolume = BigDecimal::of('0');

        foreach ($depth['bids'] as $bid) {
            $totalBidVolume = $totalBidVolume->plus(BigDecimal::of($bid['amount']));
        }

        foreach ($depth['asks'] as $ask) {
            $totalAskVolume = $totalAskVolume->plus(BigDecimal::of($ask['amount']));
        }

        $depth['total_bid_volume'] = $totalBidVolume->__toString();
        $depth['total_ask_volume'] = $totalAskVolume->__toString();

        return $depth;
    }

    /**
     * Monitor price divergence across all trading pairs
     */
    public function monitorPriceDivergence(): array
    {
        $divergences = [];

        // Get all active trading pairs
        $pairs = Order::select('base_currency', 'quote_currency')
            ->distinct()
            ->get();

        foreach ($pairs as $pair) {
            $baseCurrency = $pair->base_currency;
            $quoteCurrency = $pair->quote_currency;

            // Get internal mid price
            $orderBook = $this->exchangeService->getOrderBook($baseCurrency, $quoteCurrency);
            
            if (empty($orderBook['buy_orders']) || empty($orderBook['sell_orders'])) {
                continue;
            }

            $internalBid = BigDecimal::of($orderBook['buy_orders'][0]['price']);
            $internalAsk = BigDecimal::of($orderBook['sell_orders'][0]['price']);
            $internalMid = $internalBid->plus($internalAsk)->dividedBy('2', 8, RoundingMode::HALF_UP);

            // Get external prices
            $externalPrices = [];
            foreach ($this->connectorRegistry->getConnectors() as $connector) {
                try {
                    $ticker = $connector->getTicker($baseCurrency, $quoteCurrency);
                    if ($ticker && isset($ticker['bid']) && isset($ticker['ask'])) {
                        $bid = BigDecimal::of($ticker['bid']);
                        $ask = BigDecimal::of($ticker['ask']);
                        $mid = $bid->plus($ask)->dividedBy('2', 8, RoundingMode::HALF_UP);
                        
                        $externalPrices[$connector->getName()] = [
                            'bid' => $bid->__toString(),
                            'ask' => $ask->__toString(),
                            'mid' => $mid->__toString(),
                            'divergence' => $mid->minus($internalMid)
                                ->dividedBy($internalMid, 6, RoundingMode::HALF_UP)
                                ->multipliedBy('100')
                                ->__toString()
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip failed exchanges
                }
            }

            if (!empty($externalPrices)) {
                $divergences["{$baseCurrency}/{$quoteCurrency}"] = [
                    'internal_mid' => $internalMid->__toString(),
                    'external_prices' => $externalPrices
                ];
            }
        }

        return $divergences;
    }

    /**
     * Rebalance liquidity across exchanges
     */
    public function rebalanceLiquidity(array $targetDistribution): array
    {
        // This would implement logic to move funds between exchanges
        // to maintain target distribution percentages
        return [
            'rebalanced' => false,
            'reason' => 'Cross-exchange fund transfers not yet implemented',
            'target_distribution' => $targetDistribution
        ];
    }

    /**
     * Get arbitrage statistics
     */
    public function getArbitrageStats(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $query = Trade::where('metadata->source', 'arbitrage');

        if ($from) {
            $query->where('created_at', '>=', $from);
        }

        if ($to) {
            $query->where('created_at', '<=', $to);
        }

        $trades = $query->get();

        $stats = [
            'total_trades' => $trades->count(),
            'total_volume' => '0',
            'total_profit' => '0',
            'by_exchange' => [],
            'by_pair' => []
        ];

        $totalVolume = BigDecimal::of('0');
        $totalProfit = BigDecimal::of('0');
        $byExchange = [];
        $byPair = [];

        foreach ($trades as $trade) {
            $volume = BigDecimal::of($trade->amount)->multipliedBy($trade->price);
            $totalVolume = $totalVolume->plus($volume);

            // Extract profit from metadata if available
            if (isset($trade->metadata['profit'])) {
                $profit = BigDecimal::of($trade->metadata['profit']);
                $totalProfit = $totalProfit->plus($profit);
            }

            // Group by exchange
            $exchange = $trade->metadata['external_exchange'] ?? 'unknown';
            if (!isset($byExchange[$exchange])) {
                $byExchange[$exchange] = [
                    'trades' => 0,
                    'volume' => BigDecimal::of('0'),
                    'profit' => BigDecimal::of('0')
                ];
            }
            $byExchange[$exchange]['trades']++;
            $byExchange[$exchange]['volume'] = $byExchange[$exchange]['volume']->plus($volume);
            if (isset($trade->metadata['profit'])) {
                $byExchange[$exchange]['profit'] = $byExchange[$exchange]['profit']->plus($profit);
            }

            // Group by pair
            $pair = "{$trade->base_currency}/{$trade->quote_currency}";
            if (!isset($byPair[$pair])) {
                $byPair[$pair] = [
                    'trades' => 0,
                    'volume' => BigDecimal::of('0'),
                    'profit' => BigDecimal::of('0')
                ];
            }
            $byPair[$pair]['trades']++;
            $byPair[$pair]['volume'] = $byPair[$pair]['volume']->plus($volume);
            if (isset($trade->metadata['profit'])) {
                $byPair[$pair]['profit'] = $byPair[$pair]['profit']->plus($profit);
            }
        }

        // Convert BigDecimal to strings
        $stats['total_volume'] = $totalVolume->__toString();
        $stats['total_profit'] = $totalProfit->__toString();

        foreach ($byExchange as $exchange => $data) {
            $stats['by_exchange'][$exchange] = [
                'trades' => $data['trades'],
                'volume' => $data['volume']->__toString(),
                'profit' => $data['profit']->__toString()
            ];
        }

        foreach ($byPair as $pair => $data) {
            $stats['by_pair'][$pair] = [
                'trades' => $data['trades'],
                'volume' => $data['volume']->__toString(),
                'profit' => $data['profit']->__toString()
            ];
        }

        return $stats;
    }
}