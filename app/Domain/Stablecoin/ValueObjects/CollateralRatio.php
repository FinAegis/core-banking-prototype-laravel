<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\ValueObjects;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final class CollateralRatio
{
    private BigDecimal $value;

    public function __construct(float|BigDecimal $value)
    {
        $this->value = $value instanceof BigDecimal ? $value : BigDecimal::of($value);

        if ($this->value->isLessThan(0)) {
            throw new InvalidArgumentException('Collateral ratio cannot be negative');
        }
    }

    public static function fromPercentage(float $percentage): self
    {
        return new self($percentage / 100);
    }

    public function value(): BigDecimal
    {
        return $this->value;
    }

    public function toPercentage(): float
    {
        return $this->value->multipliedBy(100)->toFloat();
    }

    public function isHealthy(LiquidationThreshold $threshold): bool
    {
        return $this->value->isGreaterThanOrEqualTo($threshold->safeLevel());
    }

    public function requiresMarginCall(LiquidationThreshold $threshold): bool
    {
        return $this->value->isLessThan($threshold->marginCallLevel());
    }

    public function requiresLiquidation(LiquidationThreshold $threshold): bool
    {
        return $this->value->isLessThan($threshold->liquidationLevel());
    }

    public function equals(self $other): bool
    {
        return $this->value->isEqualTo($other->value);
    }

    public function toString(): string
    {
        return $this->value->toScale(4)->__toString();
    }
}
