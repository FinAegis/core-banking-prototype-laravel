<?php

namespace App\Domain\Exchange\Aggregates;

use App\Domain\Exchange\Events\OrderCancelled;
use App\Domain\Exchange\Events\OrderMatched;
use App\Domain\Exchange\Events\OrderPlaced;
use App\Domain\Exchange\Events\OrderPartiallyFilled;
use App\Domain\Exchange\Events\OrderFilled;
use App\Domain\Exchange\ValueObjects\OrderStatus;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class Order extends AggregateRoot
{
    protected string $orderId;
    protected string $accountId;
    protected string $type;
    protected string $orderType;
    protected string $baseCurrency;
    protected string $quoteCurrency;
    protected BigDecimal $amount;
    protected ?BigDecimal $price = null;
    protected BigDecimal $filledAmount;
    protected OrderStatus $status;
    protected array $trades = [];

    public function placeOrder(
        string $orderId,
        string $accountId,
        string $type,
        string $orderType,
        string $baseCurrency,
        string $quoteCurrency,
        string $amount,
        ?string $price = null,
        ?string $stopPrice = null,
        array $metadata = []
    ): self {
        $this->recordThat(new OrderPlaced(
            orderId: $orderId,
            accountId: $accountId,
            type: $type,
            orderType: $orderType,
            baseCurrency: $baseCurrency,
            quoteCurrency: $quoteCurrency,
            amount: $amount,
            price: $price,
            stopPrice: $stopPrice,
            metadata: $metadata
        ));

        return $this;
    }

    public function matchOrder(
        string $matchedOrderId,
        string $tradeId,
        string $executedPrice,
        string $executedAmount,
        string $makerFee,
        string $takerFee,
        array $metadata = []
    ): self {
        $this->recordThat(new OrderMatched(
            orderId: $this->orderId,
            matchedOrderId: $matchedOrderId,
            tradeId: $tradeId,
            executedPrice: $executedPrice,
            executedAmount: $executedAmount,
            makerFee: $makerFee,
            takerFee: $takerFee,
            metadata: $metadata
        ));

        return $this;
    }

    public function cancelOrder(string $reason, array $metadata = []): self
    {
        if ($this->status->isFinal()) {
            throw new \DomainException('Cannot cancel order in final status: ' . $this->status->value);
        }

        $this->recordThat(new OrderCancelled(
            orderId: $this->orderId,
            reason: $reason,
            metadata: $metadata
        ));

        return $this;
    }

    protected function applyOrderPlaced(OrderPlaced $event): void
    {
        $this->orderId = $event->orderId;
        $this->accountId = $event->accountId;
        $this->type = $event->type;
        $this->orderType = $event->orderType;
        $this->baseCurrency = $event->baseCurrency;
        $this->quoteCurrency = $event->quoteCurrency;
        $this->amount = BigDecimal::of($event->amount);
        $this->price = $event->price ? BigDecimal::of($event->price) : null;
        $this->filledAmount = BigDecimal::zero();
        $this->status = OrderStatus::PENDING;
    }

    protected function applyOrderMatched(OrderMatched $event): void
    {
        $this->trades[] = [
            'tradeId' => $event->tradeId,
            'matchedOrderId' => $event->matchedOrderId,
            'executedPrice' => $event->executedPrice,
            'executedAmount' => $event->executedAmount,
            'makerFee' => $event->makerFee,
            'takerFee' => $event->takerFee,
        ];

        $this->filledAmount = $this->filledAmount->plus($event->executedAmount);

        if ($this->filledAmount->isEqualTo($this->amount)) {
            $this->status = OrderStatus::FILLED;
        } else {
            $this->status = OrderStatus::PARTIALLY_FILLED;
        }
    }

    protected function applyOrderCancelled(OrderCancelled $event): void
    {
        $this->status = OrderStatus::CANCELLED;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getRemainingAmount(): BigDecimal
    {
        return $this->amount->minus($this->filledAmount);
    }

    public function isBuyOrder(): bool
    {
        return $this->type === 'buy';
    }

    public function isSellOrder(): bool
    {
        return $this->type === 'sell';
    }

    public function isMarketOrder(): bool
    {
        return $this->orderType === 'market';
    }

    public function isLimitOrder(): bool
    {
        return $this->orderType === 'limit';
    }
}