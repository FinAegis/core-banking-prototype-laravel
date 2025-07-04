<?php

namespace App\Domain\Exchange\ValueObjects;

use Brick\Math\BigDecimal;

final class MarketPair
{
    public function __construct(
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly BigDecimal $minOrderSize,
        public readonly BigDecimal $maxOrderSize,
        public readonly BigDecimal $tickSize,
        public readonly int $pricePrecision,
        public readonly int $amountPrecision,
        public readonly bool $isActive,
        public readonly array $metadata = []
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            baseCurrency: $data['base_currency'],
            quoteCurrency: $data['quote_currency'],
            minOrderSize: BigDecimal::of($data['min_order_size']),
            maxOrderSize: BigDecimal::of($data['max_order_size']),
            tickSize: BigDecimal::of($data['tick_size']),
            pricePrecision: $data['price_precision'],
            amountPrecision: $data['amount_precision'],
            isActive: $data['is_active'],
            metadata: $data['metadata'] ?? []
        );
    }

    public function toArray(): array
    {
        return [
            'base_currency' => $this->baseCurrency,
            'quote_currency' => $this->quoteCurrency,
            'min_order_size' => $this->minOrderSize->__toString(),
            'max_order_size' => $this->maxOrderSize->__toString(),
            'tick_size' => $this->tickSize->__toString(),
            'price_precision' => $this->pricePrecision,
            'amount_precision' => $this->amountPrecision,
            'is_active' => $this->isActive,
            'metadata' => $this->metadata,
        ];
    }

    public function getSymbol(string $separator = '/'): string
    {
        return $this->baseCurrency . $separator . $this->quoteCurrency;
    }

    public function validatePrice(BigDecimal $price): bool
    {
        if ($price->isLessThanOrEqualTo(0)) {
            return false;
        }

        // Check if price respects tick size
        $remainder = $price->remainder($this->tickSize);
        return $remainder->isZero();
    }

    public function validateAmount(BigDecimal $amount): bool
    {
        return $amount->isGreaterThanOrEqualTo($this->minOrderSize) 
            && $amount->isLessThanOrEqualTo($this->maxOrderSize);
    }
}