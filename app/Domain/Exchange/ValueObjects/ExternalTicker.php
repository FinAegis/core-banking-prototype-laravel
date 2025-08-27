<?php

namespace App\Domain\Exchange\ValueObjects;

use Brick\Math\BigDecimal;
use DateTimeImmutable;

final class ExternalTicker
{
    public function __construct(
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly BigDecimal $bid,
        public readonly BigDecimal $ask,
        public readonly BigDecimal $last,
        public readonly BigDecimal $volume24h,
        public readonly BigDecimal $high24h,
        public readonly BigDecimal $low24h,
        public readonly BigDecimal $change24h,
        public readonly DateTimeImmutable $timestamp,
        public readonly string $exchange,
        public readonly array $metadata = []
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            baseCurrency: $data['base_currency'],
            quoteCurrency: $data['quote_currency'],
            bid: BigDecimal::of($data['bid']),
            ask: BigDecimal::of($data['ask']),
            last: BigDecimal::of($data['last']),
            volume24h: BigDecimal::of($data['volume_24h']),
            high24h: BigDecimal::of($data['high_24h']),
            low24h: BigDecimal::of($data['low_24h']),
            change24h: BigDecimal::of($data['change_24h']),
            timestamp: new DateTimeImmutable($data['timestamp']),
            exchange: $data['exchange'],
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'base_currency'  => $this->baseCurrency,
            'quote_currency' => $this->quoteCurrency,
            'bid'            => $this->bid->__toString(),
            'ask'            => $this->ask->__toString(),
            'last'           => $this->last->__toString(),
            'volume_24h'     => $this->volume24h->__toString(),
            'high_24h'       => $this->high24h->__toString(),
            'low_24h'        => $this->low24h->__toString(),
            'change_24h'     => $this->change24h->__toString(),
            'timestamp'      => $this->timestamp->format('c'),
            'exchange'       => $this->exchange,
            'metadata'       => $this->metadata,
        ];
    }

    public function getSpread(): BigDecimal
    {
        return $this->ask->minus($this->bid);
    }

    public function getSpreadPercentage(): BigDecimal
    {
        if ($this->bid->isZero()) {
            return BigDecimal::zero();
        }

        return $this->getSpread()->dividedBy($this->bid, 18)->multipliedBy(100);
    }

    public function getMidPrice(): BigDecimal
    {
        return $this->bid->plus($this->ask)->dividedBy(2, 18);
    }
}
