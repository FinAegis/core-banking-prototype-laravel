<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\ValueObjects;

final class AuctionResult
{
    public function __construct(
        public readonly bool $hasWinner,
        public readonly ?string $winnerId,
        public readonly float $bidAmount,
        public readonly array $collateralAmount,
        public readonly array $excessCollateral
    ) {
    }

    public function hasWinner(): bool
    {
        return $this->hasWinner;
    }

    public function getWinnerId(): ?string
    {
        return $this->winnerId;
    }

    public function getBidAmount(): float
    {
        return $this->bidAmount;
    }

    public function getCollateralAmount(): array
    {
        return $this->collateralAmount;
    }

    public function hasExcessCollateral(): bool
    {
        return ! empty($this->excessCollateral);
    }

    public function getExcessCollateral(): array
    {
        return $this->excessCollateral;
    }
}
