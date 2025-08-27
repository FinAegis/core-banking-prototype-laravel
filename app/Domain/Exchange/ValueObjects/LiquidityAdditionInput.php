<?php

namespace App\Domain\Exchange\ValueObjects;

use InvalidArgumentException;

final class LiquidityAdditionInput
{
    public function __construct(
        public readonly string $poolId,
        public readonly string $providerId,
        public readonly string $baseCurrency,
        public readonly string $quoteCurrency,
        public readonly string $baseAmount,
        public readonly string $quoteAmount,
        public readonly string $minShares = '0',
        public readonly array $metadata = []
    ) {
        if ($baseAmount <= 0 || $quoteAmount <= 0) {
            throw new InvalidArgumentException('Liquidity amounts must be positive');
        }
    }

    public function toArray(): array
    {
        return [
            'pool_id'        => $this->poolId,
            'provider_id'    => $this->providerId,
            'base_currency'  => $this->baseCurrency,
            'quote_currency' => $this->quoteCurrency,
            'base_amount'    => $this->baseAmount,
            'quote_amount'   => $this->quoteAmount,
            'min_shares'     => $this->minShares,
            'metadata'       => $this->metadata,
        ];
    }
}
