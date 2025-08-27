<?php

declare(strict_types=1);

namespace App\Domain\Basket\Events;

use DateTimeInterface;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BasketDecomposed extends ShouldBeStored
{
    public function __construct(
        public readonly string $accountUuid,
        public readonly string $basketCode,
        public readonly int $amount,
        public readonly array $componentAmounts,
        public readonly DateTimeInterface $decomposedAt
    ) {
    }

    /**
     * Get the total value of decomposed components.
     */
    public function getTotalComponentValue(): int
    {
        return array_sum(array_column($this->componentAmounts, 'amount'));
    }

    /**
     * Get the assets received from decomposition.
     */
    public function getReceivedAssets(): array
    {
        return array_keys($this->componentAmounts);
    }
}
