<?php

namespace App\Domain\Exchange\ValueObjects;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final class ExternalOrderBook
{
    /**
     * @param Collection<array{price: BigDecimal, amount: BigDecimal}> $bids
     * @param Collection<array{price: BigDecimal, amount: BigDecimal}> $asks
     */
    public function __construct(
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly Collection $bids,
        public readonly Collection $asks,
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
            bids: collect($data['bids'])->map(
                fn ($bid) => [
                'price'  => BigDecimal::of($bid['price']),
                'amount' => BigDecimal::of($bid['amount']),
                ]
            ),
            asks: collect($data['asks'])->map(
                fn ($ask) => [
                'price'  => BigDecimal::of($ask['price']),
                'amount' => BigDecimal::of($ask['amount']),
                ]
            ),
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
            'bids'           => $this->bids->map(
                fn ($bid) => [
                'price'  => $bid['price']->__toString(),
                'amount' => $bid['amount']->__toString(),
                ]
            )->toArray(),
            'asks' => $this->asks->map(
                fn ($ask) => [
                'price'  => $ask['price']->__toString(),
                'amount' => $ask['amount']->__toString(),
                ]
            )->toArray(),
            'timestamp' => $this->timestamp->format('c'),
            'exchange'  => $this->exchange,
            'metadata'  => $this->metadata,
        ];
    }

    public function getBestBid(): ?array
    {
        return $this->bids->first();
    }

    public function getBestAsk(): ?array
    {
        return $this->asks->first();
    }

    public function getSpread(): ?BigDecimal
    {
        $bestBid = $this->getBestBid();
        $bestAsk = $this->getBestAsk();

        if (! $bestBid || ! $bestAsk) {
            return null;
        }

        return $bestAsk['price']->minus($bestBid['price']);
    }

    public function getMidPrice(): ?BigDecimal
    {
        $bestBid = $this->getBestBid();
        $bestAsk = $this->getBestAsk();

        if (! $bestBid || ! $bestAsk) {
            return null;
        }

        return $bestBid['price']->plus($bestAsk['price'])->dividedBy(2, 18);
    }

    public function getTotalBidVolume(): BigDecimal
    {
        return $this->bids->reduce(
            fn (BigDecimal $carry, $bid) => $carry->plus($bid['amount']),
            BigDecimal::zero()
        );
    }

    public function getTotalAskVolume(): BigDecimal
    {
        return $this->asks->reduce(
            fn (BigDecimal $carry, $ask) => $carry->plus($ask['amount']),
            BigDecimal::zero()
        );
    }
}
