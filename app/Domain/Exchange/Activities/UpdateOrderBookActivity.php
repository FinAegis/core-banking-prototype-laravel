<?php

namespace App\Domain\Exchange\Activities;

use App\Domain\Exchange\Aggregates\OrderBook;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\ValueObjects\OrderBookEntry;
use App\Domain\Exchange\ValueObjects\Money;
use Workflow\Activity;

class UpdateOrderBookActivity extends Activity
{
    public function execute(
        string $orderId,
        string $action,
        ?object $matchingResult = null
    ): object {
        try {
            // Get order details
            $order = Order::query()
                ->where('order_id', $orderId)
                ->firstOrFail();
            
            $orderBookId = OrderBook::generateId($order->base_asset, $order->quote_asset);
            $orderBook = OrderBook::retrieve($orderBookId);
            
            switch ($action) {
                case 'add':
                    // Add order to order book
                    $orderBook->addOrder(
                        orderId: $orderId,
                        type: $order->side,
                        price: $order->price,
                        amount: $order->remaining_amount,
                        metadata: [
                            'account_id' => $order->account_id,
                            'order_type' => $order->type,
                            'timestamp' => now()->toIso8601String()
                        ]
                    )->persist();
                    break;
                    
                case 'remove':
                    // Remove order from order book
                    $orderBook->removeOrder($orderId, 'order_cancelled', [])->persist();
                    break;
                    
                case 'update_after_match':
                    // Update order book after matching
                    if ($matchingResult && isset($matchingResult->matches)) {
                        foreach ($matchingResult->matches as $match) {
                            // Update or remove orders based on their remaining amounts
                            $buyOrder = Order::query()
                                ->where('order_id', $match->buyOrderId)
                                ->first();
                                
                            $sellOrder = Order::query()
                                ->where('order_id', $match->sellOrderId)
                                ->first();
                            
                            // Remove filled orders
                            if ($buyOrder && bccomp($buyOrder->remaining_amount, '0', 18) <= 0) {
                                $orderBook->removeOrder($match->buyOrderId, 'order_filled', [
                                    'trade_id' => $match->tradeId
                                ]);
                            }
                            
                            if ($sellOrder && bccomp($sellOrder->remaining_amount, '0', 18) <= 0) {
                                $orderBook->removeOrder($match->sellOrderId, 'order_filled', [
                                    'trade_id' => $match->tradeId
                                ]);
                            }
                            
                            // For partially filled orders, we need to remove and re-add with new amount
                            if ($buyOrder && bccomp($buyOrder->remaining_amount, '0', 18) > 0) {
                                $orderBook->removeOrder($match->buyOrderId, 'partial_fill', [])
                                    ->addOrder(
                                        orderId: $match->buyOrderId,
                                        type: $buyOrder->side,
                                        price: $buyOrder->price,
                                        amount: $buyOrder->remaining_amount,
                                        metadata: [
                                            'account_id' => $buyOrder->account_id,
                                            'order_type' => $buyOrder->type,
                                            'partially_filled' => true
                                        ]
                                    );
                            }
                            
                            if ($sellOrder && bccomp($sellOrder->remaining_amount, '0', 18) > 0) {
                                $orderBook->removeOrder($match->sellOrderId, 'partial_fill', [])
                                    ->addOrder(
                                        orderId: $match->sellOrderId,
                                        type: $sellOrder->side,
                                        price: $sellOrder->price,
                                        amount: $sellOrder->remaining_amount,
                                        metadata: [
                                            'account_id' => $sellOrder->account_id,
                                            'order_type' => $sellOrder->type,
                                            'partially_filled' => true
                                        ]
                                    );
                            }
                        }
                        
                        $orderBook->persist();
                    }
                    break;
                    
                default:
                    throw new \InvalidArgumentException("Unknown action: {$action}");
            }
            
            return (object) [
                'success' => true,
                'action' => $action,
                'orderId' => $orderId
            ];
            
        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}