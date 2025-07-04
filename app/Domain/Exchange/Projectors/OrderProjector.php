<?php

namespace App\Domain\Exchange\Projectors;

use App\Domain\Exchange\Events\OrderCancelled;
use App\Domain\Exchange\Events\OrderFilled;
use App\Domain\Exchange\Events\OrderMatched;
use App\Domain\Exchange\Events\OrderPartiallyFilled;
use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Projections\Order;
use App\Domain\Exchange\Projections\Trade;
use Brick\Math\BigDecimal;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class OrderProjector extends Projector
{
    public function onOrderPlaced(OrderPlaced $event): void
    {
        Order::create([
            'order_id' => $event->orderId,
            'account_id' => $event->accountId,
            'type' => $event->type,
            'order_type' => $event->orderType,
            'base_currency' => $event->baseCurrency,
            'quote_currency' => $event->quoteCurrency,
            'amount' => $event->amount,
            'filled_amount' => '0',
            'price' => $event->price,
            'stop_price' => $event->stopPrice,
            'status' => 'pending',
            'trades' => json_encode([]),
            'metadata' => $event->metadata ? json_encode($event->metadata) : null,
        ]);
    }

    public function onOrderMatched(OrderMatched $event): void
    {
        $order = Order::where('order_id', $event->orderId)->firstOrFail();
        
        // Update order with trade information
        $trades = $order->trades ?? [];
        $trades[] = [
            'trade_id' => $event->tradeId,
            'matched_order_id' => $event->matchedOrderId,
            'executed_price' => $event->executedPrice,
            'executed_amount' => $event->executedAmount,
            'fee' => $order->type === 'buy' ? $event->takerFee : $event->makerFee,
            'timestamp' => now()->toIso8601String(),
        ];
        
        $filledAmount = BigDecimal::of($order->filled_amount)->plus($event->executedAmount);
        
        // Calculate average price
        $totalValue = BigDecimal::zero();
        $totalAmount = BigDecimal::zero();
        foreach ($trades as $trade) {
            $tradeValue = BigDecimal::of($trade['executed_price'])->multipliedBy($trade['executed_amount']);
            $totalValue = $totalValue->plus($tradeValue);
            $totalAmount = $totalAmount->plus($trade['executed_amount']);
        }
        $averagePrice = $totalValue->dividedBy($totalAmount, 18);
        
        $order->update([
            'filled_amount' => $filledAmount->__toString(),
            'average_price' => $averagePrice->__toString(),
            'trades' => $trades,
            'status' => $filledAmount->isEqualTo($order->amount) ? 'filled' : 'partially_filled',
        ]);
        
        // Create trade record
        $matchedOrder = Order::where('order_id', $event->matchedOrderId)->firstOrFail();
        
        Trade::create([
            'trade_id' => $event->tradeId,
            'buy_order_id' => $order->type === 'buy' ? $order->order_id : $matchedOrder->order_id,
            'sell_order_id' => $order->type === 'sell' ? $order->order_id : $matchedOrder->order_id,
            'buyer_account_id' => $order->type === 'buy' ? $order->account_id : $matchedOrder->account_id,
            'seller_account_id' => $order->type === 'sell' ? $order->account_id : $matchedOrder->account_id,
            'base_currency' => $order->base_currency,
            'quote_currency' => $order->quote_currency,
            'price' => $event->executedPrice,
            'amount' => $event->executedAmount,
            'value' => BigDecimal::of($event->executedPrice)->multipliedBy($event->executedAmount)->__toString(),
            'maker_fee' => $event->makerFee,
            'taker_fee' => $event->takerFee,
            'maker_side' => $order->type === 'buy' ? 'sell' : 'buy', // Maker is the opposite side
            'metadata' => $event->metadata,
        ]);
    }

    public function onOrderPartiallyFilled(OrderPartiallyFilled $event): void
    {
        Order::where('order_id', $event->orderId)->update([
            'status' => 'partially_filled',
        ]);
    }

    public function onOrderFilled(OrderFilled $event): void
    {
        Order::where('order_id', $event->orderId)->update([
            'status' => 'filled',
            'filled_at' => now(),
        ]);
    }

    public function onOrderCancelled(OrderCancelled $event): void
    {
        Order::where('order_id', $event->orderId)->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'metadata' => json_encode(array_merge(
                Order::where('order_id', $event->orderId)->value('metadata') ?? [],
                ['cancellation_reason' => $event->reason]
            )),
        ]);
    }
}