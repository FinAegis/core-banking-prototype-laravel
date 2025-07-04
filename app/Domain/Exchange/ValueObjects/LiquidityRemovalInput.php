<?php

namespace App\Domain\Exchange\ValueObjects;

final class LiquidityRemovalInput
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $providerId,
        public readonly string $shares,
        public readonly string $minBaseAmount = '0',
        public readonly string $minQuoteAmount = '0',
        public readonly array $metadata = []
    ) {
        if ($shares <= 0) {
            throw new \InvalidArgumentException('Shares must be positive');
        }
    }

    public function toArray(): array
    {
        return [
            'pool_id' => $this->poolId,
            'provider_id' => $this->providerId,
            'shares' => $this->shares,
            'min_base_amount' => $this->minBaseAmount,
            'min_quote_amount' => $this->minQuoteAmount,
            'metadata' => $this->metadata,
        ];
    }
}