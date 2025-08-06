<?php

declare(strict_types=1);

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Events\OrderCancelled;
use App\Domain\Exchange\Events\OrderMatched;
use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Projections\Trade;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoExchangeService
{
    public function __construct()
    {
        // Demo service doesn't need external dependencies
    }

    /**
     * Place a demo order with simulated instant matching.
     */
    public function placeOrder(array $data): Order
    {
        $orderId = 'demo_ord_' . Str::random(16);

        // Create the order
        $order = DB::transaction(function () use ($data, $orderId) {
            $order = Order::create([
                'id'             => $orderId,
                'user_id'        => $data['user_id'],
                'account_id'     => $data['account_id'],
                'type'           => $data['type'],
                'side'           => $data['side'],
                'base_currency'  => $data['base_currency'],
                'quote_currency' => $data['quote_currency'],
                'amount'         => $data['amount'],
                'price'          => $data['price'] ?? $this->getSimulatedPrice($data['base_currency'], $data['quote_currency']),
                'status'         => 'pending',
                'metadata'       => array_merge($data['metadata'] ?? [], ['demo_mode' => true]),
            ]);

            // Record order placed event
            event(new OrderPlaced(
                orderId: $order->id,
                accountId: (string) $order->account_id,
                type: $order->side,
                orderType: $order->type,
                baseCurrency: $order->base_currency,
                quoteCurrency: $order->quote_currency,
                amount: (string) $order->amount,
                price: $order->price ? (string) $order->price : null,
                metadata: $order->metadata ?? []
            ));

            // Simulate instant matching for demo mode
            if (config('demo.features.auto_fill_orders', true)) {
                $this->simulateOrderMatching($order);
            }

            return $order;
        });

        return $order;
    }

    /**
     * Cancel a demo order.
     */
    public function cancelOrder(string $orderId, int $userId): bool
    {
        return DB::transaction(function () use ($orderId, $userId) {
            $order = Order::where('id', $orderId)
                ->where('user_id', $userId)
                ->whereIn('status', ['pending', 'partially_filled'])
                ->first();

            if (! $order) {
                return false;
            }

            $order->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            event(new OrderCancelled(
                orderId: $order->id,
                reason: 'User requested cancellation'
            ));

            return true;
        });
    }

    /**
     * Get simulated order book with demo liquidity.
     */
    public function getOrderBook(string $baseCurrency, string $quoteCurrency, int $depth = 10): array
    {
        $cacheKey = "demo_orderbook_{$baseCurrency}_{$quoteCurrency}";

        return Cache::remember($cacheKey, 5, function () use ($baseCurrency, $quoteCurrency, $depth) {
            $midPrice = $this->getSimulatedPrice($baseCurrency, $quoteCurrency);
            $spread = config('demo.demo_data.exchange.spread_percentage', 0.1) / 100;

            // Generate demo buy orders
            $bids = [];
            for ($i = 1; $i <= $depth; $i++) {
                $priceMultiplier = 1 - ($spread * $i);
                $price = round($midPrice * $priceMultiplier, 4);
                $amount = $this->getRandomAmount();

                $bids[] = [
                    'price'  => (string) $price,
                    'amount' => (string) $amount,
                    'total'  => (string) round($price * $amount, 2),
                ];
            }

            // Generate demo sell orders
            $asks = [];
            for ($i = 1; $i <= $depth; $i++) {
                $priceMultiplier = 1 + ($spread * $i);
                $price = round($midPrice * $priceMultiplier, 4);
                $amount = $this->getRandomAmount();

                $asks[] = [
                    'price'  => (string) $price,
                    'amount' => (string) $amount,
                    'total'  => (string) round($price * $amount, 2),
                ];
            }

            return [
                'pair'      => "{$baseCurrency}/{$quoteCurrency}",
                'bids'      => $bids,
                'asks'      => $asks,
                'timestamp' => now()->toIso8601String(),
                'demo'      => true,
            ];
        });
    }

    /**
     * Get simulated market data.
     */
    public function getMarketData(string $baseCurrency, string $quoteCurrency): array
    {
        $price = $this->getSimulatedPrice($baseCurrency, $quoteCurrency);
        $change = rand(-500, 500) / 100; // -5% to +5% change

        return [
            'pair'                  => "{$baseCurrency}/{$quoteCurrency}",
            'last_price'            => (string) $price,
            'bid'                   => (string) round($price * 0.999, 4),
            'ask'                   => (string) round($price * 1.001, 4),
            'volume_24h'            => (string) rand(10000, 1000000),
            'change_24h'            => (string) $change,
            'change_percentage_24h' => (string) round($change / $price * 100, 2),
            'high_24h'              => (string) round($price * 1.05, 4),
            'low_24h'               => (string) round($price * 0.95, 4),
            'timestamp'             => now()->toIso8601String(),
            'demo'                  => true,
        ];
    }

    /**
     * Simulate instant order matching for demo mode.
     */
    private function simulateOrderMatching(Order $order): void
    {
        // Calculate fees using demo values (0.1% for maker, 0.2% for taker)
        $price = $order->price ?? $this->getSimulatedPrice($order->base_currency, $order->quote_currency);
        $value = $order->amount * $price;
        $fee = $order->side === 'buy' ? $value * 0.002 : $value * 0.002; // 0.2% taker fee for demo

        // Create a matching trade
        $trade = Trade::create([
            'id'             => 'demo_trd_' . Str::random(16),
            'order_id'       => $order->id,
            'user_id'        => $order->user_id,
            'account_id'     => $order->account_id,
            'base_currency'  => $order->base_currency,
            'quote_currency' => $order->quote_currency,
            'side'           => $order->side,
            'amount'         => $order->amount,
            'price'          => $order->price ?? $this->getSimulatedPrice($order->base_currency, $order->quote_currency),
            'fee'            => $fee,
            'fee_currency'   => $order->quote_currency,
            'status'         => 'completed',
            'executed_at'    => now(),
            'metadata'       => ['demo_mode' => true, 'instant_fill' => true],
        ]);

        // Update order status
        $order->update([
            'status'           => 'filled',
            'filled_amount'    => $order->amount,
            'remaining_amount' => 0,
            'average_price'    => $trade->price,
            'filled_at'        => now(),
        ]);

        // Fire events
        event(new OrderMatched(
            orderId: $order->id,
            matchedOrderId: $order->id, // Self-matched in demo
            tradeId: $trade->id,
            executedPrice: (string) $trade->price,
            executedAmount: (string) $trade->amount,
            makerFee: '0',
            takerFee: (string) $fee
        ));
    }

    /**
     * Get simulated price for a currency pair.
     */
    private function getSimulatedPrice(string $baseCurrency, string $quoteCurrency): float
    {
        $pair = "{$baseCurrency}/{$quoteCurrency}";
        $basePrices = config('demo.demo_data.exchange.prices', [
            'EUR/USD' => 1.10,
            'GBP/USD' => 1.27,
            'GCU/USD' => 1.00,
            'BTC/USD' => 45000,
            'ETH/USD' => 2500,
        ]);

        // Check if we have a direct price
        if (isset($basePrices[$pair])) {
            $basePrice = $basePrices[$pair];
        } elseif (isset($basePrices["{$quoteCurrency}/{$baseCurrency}"])) {
            // Check for inverse pair
            $basePrice = 1 / $basePrices["{$quoteCurrency}/{$baseCurrency}"];
        } else {
            // Default to 1.0
            $basePrice = 1.0;
        }

        // Add slight variation for realism
        $variation = rand(-100, 100) / 10000; // ±0.01 variation

        return round($basePrice + $variation, 4);
    }

    /**
     * Generate random amount for order book.
     */
    private function getRandomAmount(): float
    {
        $multiplier = config('demo.demo_data.exchange.liquidity_multiplier', 10);

        return round(rand(10, 1000) * $multiplier / 10, 2);
    }
}
