<?php

namespace App\Domain\Exchange\ValueObjects;

use Brick\Math\BigDecimal;

final class ExternalTrade
{
    public function __construct(
        public readonly string $tradeId,
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly BigDecimal $price,
        public readonly BigDecimal $amount,
        public readonly string $side, // 'buy' or 'sell'
        public readonly \DateTimeImmutable $timestamp,
        public readonly string $exchange,
        public readonly array $metadata = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            tradeId: $data['trade_id'],
            baseCurrency: $data['base_currency'],
            quoteCurrency: $data['quote_currency'],
            price: BigDecimal::of($data['price']),
            amount: BigDecimal::of($data['amount']),
            side: $data['side'],
            timestamp: new \DateTimeImmutable($data['timestamp']),
            exchange: $data['exchange'],
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'trade_id' => $this->tradeId,
            'base_currency' => $this->baseCurrency,
            'quote_currency' => $this->quoteCurrency,
            'price' => $this->price->__toString(),
            'amount' => $this->amount->__toString(),
            'side' => $this->side,
            'timestamp' => $this->timestamp->format('c'),
            'exchange' => $this->exchange,
            'metadata' => $this->metadata,
        ];
    }

    public function getValue(): BigDecimal
    {
        return $this->price->multipliedBy($this->amount);
    }
}