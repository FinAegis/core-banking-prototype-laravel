<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Aggregates\Order;
use App\Domain\Exchange\Aggregates\OrderBook;
use App\Domain\Exchange\Projections\OrderBook as OrderBookProjection;
use App\Domain\Exchange\ValueObjects\OrderMatchingInput;
use App\Domain\Exchange\Workflows\OrderMatchingWorkflow;
use App\Models\Account;
use App\Models\Asset;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

class ExchangeService
{
    private FeeCalculator $feeCalculator;
    
    public function __construct()
    {
        $this->feeCalculator = new FeeCalculator();
    }
    
    public function placeOrder(
        string $accountId,
        string $type,
        string $orderType,
        string $baseCurrency,
        string $quoteCurrency,
        string $amount,
        ?string $price = null,
        ?string $stopPrice = null,
        array $metadata = []
    ): array {
        // Validate account
        $account = Account::find($accountId);
        if (!$account) {
            throw new \InvalidArgumentException('Account not found');
        }
        
        // Validate currencies
        $baseAsset = Asset::where('code', $baseCurrency)->first();
        $quoteAsset = Asset::where('code', $quoteCurrency)->first();
        
        if (!$baseAsset || !$quoteAsset) {
            throw new \InvalidArgumentException('Invalid currency pair');
        }
        
        if (!$baseAsset->is_tradeable || !$quoteAsset->is_tradeable) {
            throw new \InvalidArgumentException('Currency pair not available for trading');
        }
        
        // Validate order type and price
        if ($orderType === 'limit' && !$price) {
            throw new \InvalidArgumentException('Price is required for limit orders');
        }
        
        if ($orderType === 'stop' && !$stopPrice) {
            throw new \InvalidArgumentException('Stop price is required for stop orders');
        }
        
        // Check minimum order value
        $minimumAmount = $this->feeCalculator->calculateMinimumOrderValue($baseCurrency, $quoteCurrency);
        if (bccomp($amount, $minimumAmount->__toString(), 18) < 0) {
            throw new \InvalidArgumentException("Minimum order amount is {$minimumAmount->__toString()} {$baseCurrency}");
        }
        
        // Generate order ID
        $orderId = Str::uuid()->toString();
        
        // Create order aggregate and place the order
        $order = Order::retrieve($orderId);
        $order->placeOrder(
            orderId: $orderId,
            accountId: $accountId,
            type: $type,
            orderType: $orderType,
            baseCurrency: $baseCurrency,
            quoteCurrency: $quoteCurrency,
            amount: $amount,
            price: $price,
            stopPrice: $stopPrice,
            metadata: array_merge($metadata, [
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'placed_at' => now()->toIso8601String(),
            ])
        );
        $order->persist();
        
        // Ensure order book exists
        $this->ensureOrderBookExists($baseCurrency, $quoteCurrency);
        
        // Start order matching workflow
        $workflow = WorkflowStub::make(OrderMatchingWorkflow::class);
        $workflow->start(new OrderMatchingInput(
            orderId: $orderId,
            maxIterations: 100
        ));
        
        return [
            'success' => true,
            'order_id' => $orderId,
            'message' => 'Order placed successfully',
            'workflow_id' => $workflow->id(),
        ];
    }
    
    public function cancelOrder(string $orderId, string $reason = 'User requested'): array
    {
        $orderProjection = \App\Domain\Exchange\Projections\Order::where('order_id', $orderId)->first();
        
        if (!$orderProjection) {
            throw new \InvalidArgumentException('Order not found');
        }
        
        if (!$orderProjection->canBeCancelled()) {
            throw new \InvalidArgumentException("Order cannot be cancelled. Status: {$orderProjection->status}");
        }
        
        // Cancel the order
        $order = Order::retrieve($orderId);
        $order->cancelOrder($reason);
        $order->persist();
        
        // Remove from order book
        $orderBook = OrderBook::retrieve($this->getOrderBookId($orderProjection->base_currency, $orderProjection->quote_currency));
        $orderBook->removeOrder($orderId, 'cancelled');
        $orderBook->persist();
        
        return [
            'success' => true,
            'message' => 'Order cancelled successfully',
        ];
    }
    
    public function getOrderBook(string $baseCurrency, string $quoteCurrency, int $depth = 20): array
    {
        $orderBook = OrderBookProjection::forPair($baseCurrency, $quoteCurrency)->first();
        
        if (!$orderBook) {
            return [
                'pair' => "{$baseCurrency}/{$quoteCurrency}",
                'bids' => [],
                'asks' => [],
                'spread' => null,
                'last_price' => null,
            ];
        }
        
        $depthData = $orderBook->getDepth($depth);
        
        return [
            'pair' => $orderBook->pair,
            'bids' => $depthData['bids'],
            'asks' => $depthData['asks'],
            'spread' => $orderBook->spread,
            'spread_percentage' => $orderBook->spread_percentage,
            'mid_price' => $orderBook->mid_price,
            'last_price' => $orderBook->last_price,
            'volume_24h' => $orderBook->volume_24h,
            'high_24h' => $orderBook->high_24h,
            'low_24h' => $orderBook->low_24h,
            'change_24h' => $orderBook->change_24h,
            'change_24h_percentage' => $orderBook->change_24h_percentage,
            'updated_at' => $orderBook->updated_at->toIso8601String(),
        ];
    }
    
    public function getMarketData(): array
    {
        $orderBooks = OrderBookProjection::all();
        
        return $orderBooks->map(function ($orderBook) {
            return [
                'pair' => $orderBook->pair,
                'last_price' => $orderBook->last_price,
                'volume_24h' => $orderBook->volume_24h,
                'change_24h_percentage' => $orderBook->change_24h_percentage,
                'high_24h' => $orderBook->high_24h,
                'low_24h' => $orderBook->low_24h,
            ];
        })->toArray();
    }
    
    private function ensureOrderBookExists(string $baseCurrency, string $quoteCurrency): void
    {
        $orderBookId = $this->getOrderBookId($baseCurrency, $quoteCurrency);
        
        $exists = OrderBookProjection::where('order_book_id', $orderBookId)->exists();
        
        if (!$exists) {
            $orderBook = OrderBook::retrieve($orderBookId);
            $orderBook->initialize(
                orderBookId: $orderBookId,
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                metadata: [
                    'created_at' => now()->toIso8601String(),
                ]
            );
            $orderBook->persist();
        }
    }
    
    private function getOrderBookId(string $baseCurrency, string $quoteCurrency): string
    {
        return "orderbook_{$baseCurrency}_{$quoteCurrency}";
    }
}