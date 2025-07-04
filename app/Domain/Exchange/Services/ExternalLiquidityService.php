<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Aggregates\Order as OrderAggregate;
use App\Domain\Exchange\Events\ExternalLiquidityProvided;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Projections\OrderBook;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ExternalLiquidityService
{
    public function __construct(
        private readonly ExternalExchangeConnectorRegistry $connectorRegistry,
        private readonly ExchangeService $exchangeService
    ) {}

    /**
     * Check for arbitrage opportunities between internal and external exchanges
     */
    public function findArbitrageOpportunities(string $baseCurrency, string $quoteCurrency): array
    {
        $opportunities = [];
        
        // Get internal order book
        $internalOrderBook = OrderBook::forPair($baseCurrency, $quoteCurrency)->first();
        if (!$internalOrderBook) {
            return $opportunities;
        }

        $internalBestBid = $internalOrderBook->best_bid ? BigDecimal::of($internalOrderBook->best_bid) : null;
        $internalBestAsk = $internalOrderBook->best_ask ? BigDecimal::of($internalOrderBook->best_ask) : null;

        // Check each external exchange
        foreach ($this->connectorRegistry->available() as $exchangeName => $connector) {
            try {
                $ticker = $connector->getTicker($baseCurrency, $quoteCurrency);
                
                // Buy from external, sell internally
                if ($internalBestBid && $ticker->ask->isLessThan($internalBestBid)) {
                    $spread = $internalBestBid->minus($ticker->ask);
                    $spreadPercent = $spread->dividedBy($ticker->ask, 18)->multipliedBy(100);
                    
                    $opportunities[] = [
                        'type' => 'buy_external_sell_internal',
                        'external_exchange' => $exchangeName,
                        'external_price' => $ticker->ask->__toString(),
                        'internal_price' => $internalBestBid->__toString(),
                        'spread' => $spread->__toString(),
                        'spread_percent' => $spreadPercent->__toString(),
                        'base_currency' => $baseCurrency,
                        'quote_currency' => $quoteCurrency,
                    ];
                }

                // Buy internally, sell to external
                if ($internalBestAsk && $ticker->bid->isGreaterThan($internalBestAsk)) {
                    $spread = $ticker->bid->minus($internalBestAsk);
                    $spreadPercent = $spread->dividedBy($internalBestAsk, 18)->multipliedBy(100);
                    
                    $opportunities[] = [
                        'type' => 'buy_internal_sell_external',
                        'external_exchange' => $exchangeName,
                        'external_price' => $ticker->bid->__toString(),
                        'internal_price' => $internalBestAsk->__toString(),
                        'spread' => $spread->__toString(),
                        'spread_percent' => $spreadPercent->__toString(),
                        'base_currency' => $baseCurrency,
                        'quote_currency' => $quoteCurrency,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning("Failed to check arbitrage with {$exchangeName}", [
                    'error' => $e->getMessage(),
                    'pair' => "{$baseCurrency}/{$quoteCurrency}"
                ]);
            }
        }

        return $opportunities;
    }

    /**
     * Provide liquidity from external exchanges when internal liquidity is low
     */
    public function provideLiquidity(string $baseCurrency, string $quoteCurrency, string $systemAccountId): void
    {
        $orderBook = OrderBook::forPair($baseCurrency, $quoteCurrency)->first();
        if (!$orderBook) {
            return;
        }

        // Check if liquidity is needed (thin order book)
        $buyOrders = $orderBook->buy_orders ?? [];
        $sellOrders = $orderBook->sell_orders ?? [];
        
        $needsBuyLiquidity = count($buyOrders) < 5;
        $needsSellLiquidity = count($sellOrders) < 5;

        if (!$needsBuyLiquidity && !$needsSellLiquidity) {
            return;
        }

        // Get best prices from external exchanges
        $bestExternalBid = $this->connectorRegistry->getBestBid($baseCurrency, $quoteCurrency);
        $bestExternalAsk = $this->connectorRegistry->getBestAsk($baseCurrency, $quoteCurrency);

        DB::transaction(function () use (
            $baseCurrency,
            $quoteCurrency,
            $systemAccountId,
            $needsBuyLiquidity,
            $needsSellLiquidity,
            $bestExternalBid,
            $bestExternalAsk
        ) {
            // Add buy orders (with markup) if needed
            if ($needsSellLiquidity && $bestExternalAsk) {
                $askPrice = BigDecimal::of($bestExternalAsk['price']);
                
                // Add multiple orders at different price levels
                for ($i = 0; $i < 5; $i++) {
                    $priceMultiplier = BigDecimal::of('1')->plus(BigDecimal::of('0.001')->multipliedBy($i)); // 0.1% increments
                    $orderPrice = $askPrice->multipliedBy($priceMultiplier);
                    $orderAmount = BigDecimal::of('0.1'); // Fixed amount for liquidity
                    
                    $orderId = Str::uuid()->toString();
                    
                    OrderAggregate::retrieve($orderId)
                        ->placeOrder(
                            accountId: $systemAccountId,
                            type: 'sell',
                            orderType: 'limit',
                            baseCurrency: $baseCurrency,
                            quoteCurrency: $quoteCurrency,
                            amount: $orderAmount->__toString(),
                            price: $orderPrice->__toString(),
                            metadata: [
                                'source' => 'external_liquidity',
                                'external_exchange' => $bestExternalAsk['exchange'],
                                'external_price' => $askPrice->__toString()
                            ]
                        )
                        ->persist();
                }
            }

            // Add sell orders (with markdown) if needed
            if ($needsBuyLiquidity && $bestExternalBid) {
                $bidPrice = BigDecimal::of($bestExternalBid['price']);
                
                // Add multiple orders at different price levels
                for ($i = 0; $i < 5; $i++) {
                    $priceMultiplier = BigDecimal::of('1')->minus(BigDecimal::of('0.001')->multipliedBy($i)); // 0.1% decrements
                    $orderPrice = $bidPrice->multipliedBy($priceMultiplier);
                    $orderAmount = BigDecimal::of('0.1'); // Fixed amount for liquidity
                    
                    $orderId = Str::uuid()->toString();
                    
                    OrderAggregate::retrieve($orderId)
                        ->placeOrder(
                            accountId: $systemAccountId,
                            type: 'buy',
                            orderType: 'limit',
                            baseCurrency: $baseCurrency,
                            quoteCurrency: $quoteCurrency,
                            amount: $orderAmount->__toString(),
                            price: $orderPrice->__toString(),
                            metadata: [
                                'source' => 'external_liquidity',
                                'external_exchange' => $bestExternalBid['exchange'],
                                'external_price' => $bidPrice->__toString()
                            ]
                        )
                        ->persist();
                }
            }

            // Emit event
            event(new ExternalLiquidityProvided(
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                buyOrdersAdded: $needsBuyLiquidity ? 5 : 0,
                sellOrdersAdded: $needsSellLiquidity ? 5 : 0,
                timestamp: now()
            ));
        });
    }

    /**
     * Mirror external market prices to keep internal prices aligned
     */
    public function alignPrices(string $baseCurrency, string $quoteCurrency, string $systemAccountId): void
    {
        $aggregatedBook = $this->connectorRegistry->getAggregatedOrderBook($baseCurrency, $quoteCurrency, 5);
        
        if (empty($aggregatedBook['bids']) || empty($aggregatedBook['asks'])) {
            return;
        }

        // Get weighted average prices from external exchanges
        $avgBid = $this->calculateWeightedAverage($aggregatedBook['bids']);
        $avgAsk = $this->calculateWeightedAverage($aggregatedBook['asks']);

        // Update or place market-making orders
        $this->updateMarketMakingOrders(
            $baseCurrency,
            $quoteCurrency,
            $systemAccountId,
            $avgBid,
            $avgAsk
        );
    }

    private function calculateWeightedAverage(array $orders): BigDecimal
    {
        $totalValue = BigDecimal::zero();
        $totalAmount = BigDecimal::zero();

        foreach ($orders as $order) {
            $price = BigDecimal::of($order['price']);
            $amount = BigDecimal::of($order['amount']);
            
            $totalValue = $totalValue->plus($price->multipliedBy($amount));
            $totalAmount = $totalAmount->plus($amount);
        }

        return $totalAmount->isZero() 
            ? BigDecimal::zero() 
            : $totalValue->dividedBy($totalAmount, 18);
    }

    private function updateMarketMakingOrders(
        string $baseCurrency,
        string $quoteCurrency,
        string $systemAccountId,
        BigDecimal $targetBid,
        BigDecimal $targetAsk
    ): void {
        // Cancel existing market-making orders
        $existingOrders = Order::where('account_id', $systemAccountId)
            ->where('base_currency', $baseCurrency)
            ->where('quote_currency', $quoteCurrency)
            ->where('status', 'pending')
            ->whereJsonContains('metadata->source', 'market_making')
            ->get();

        foreach ($existingOrders as $order) {
            OrderAggregate::retrieve($order->order_id)
                ->cancelOrder('Price adjustment', ['reason' => 'market_alignment'])
                ->persist();
        }

        // Place new market-making orders
        $spread = $targetAsk->minus($targetBid);
        $midPrice = $targetBid->plus($targetAsk)->dividedBy(2);
        
        // Place buy order slightly below mid price
        $buyPrice = $midPrice->minus($spread->multipliedBy('0.1'));
        OrderAggregate::retrieve(Str::uuid()->toString())
            ->placeOrder(
                accountId: $systemAccountId,
                type: 'buy',
                orderType: 'limit',
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                amount: '0.5',
                price: $buyPrice->__toString(),
                metadata: [
                    'source' => 'market_making',
                    'target_bid' => $targetBid->__toString(),
                    'target_ask' => $targetAsk->__toString()
                ]
            )
            ->persist();

        // Place sell order slightly above mid price
        $sellPrice = $midPrice->plus($spread->multipliedBy('0.1'));
        OrderAggregate::retrieve(Str::uuid()->toString())
            ->placeOrder(
                accountId: $systemAccountId,
                type: 'sell',
                orderType: 'limit',
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                amount: '0.5',
                price: $sellPrice->__toString(),
                metadata: [
                    'source' => 'market_making',
                    'target_bid' => $targetBid->__toString(),
                    'target_ask' => $targetAsk->__toString()
                ]
            )
            ->persist();
    }
}