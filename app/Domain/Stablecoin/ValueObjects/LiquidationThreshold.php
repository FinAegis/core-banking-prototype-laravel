<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\ValueObjects;

use Brick\Math\BigDecimal;
use InvalidArgumentException;

final class LiquidationThreshold
{
    private BigDecimal $liquidationLevel;

    private BigDecimal $marginCallLevel;

    private BigDecimal $safeLevel;

    public function __construct(float $liquidationPercentage)
    {
        if ($liquidationPercentage < 100) {
            throw new InvalidArgumentException('Liquidation threshold must be at least 100%');
        }

        if ($liquidationPercentage > 1000) {
            throw new InvalidArgumentException('Liquidation threshold cannot exceed 1000%');
        }

        // Convert percentage to decimal ratio
        $this->liquidationLevel = BigDecimal::of($liquidationPercentage)->dividedBy(100, 4);

        // Margin call at 120% of liquidation level
        $this->marginCallLevel = $this->liquidationLevel->multipliedBy('1.2');

        // Safe level at 150% of liquidation level
        $this->safeLevel = $this->liquidationLevel->multipliedBy('1.5');
    }

    public static function fromCollateralType(CollateralType $type): self
    {
        return new self($type->defaultLiquidationThreshold());
    }

    public function value(): float
    {
        return $this->liquidationLevel->toFloat();
    }

    public function liquidationLevel(): BigDecimal
    {
        return $this->liquidationLevel;
    }

    public function marginCallLevel(): BigDecimal
    {
        return $this->marginCallLevel;
    }

    public function safeLevel(): BigDecimal
    {
        return $this->safeLevel;
    }

    public function liquidationPercentage(): float
    {
        return $this->liquidationLevel->multipliedBy(100)->toFloat();
    }

    public function marginCallPercentage(): float
    {
        return $this->marginCallLevel->multipliedBy(100)->toFloat();
    }

    public function safePercentage(): float
    {
        return $this->safeLevel->multipliedBy(100)->toFloat();
    }

    public function isRatioSafe(BigDecimal $ratio): bool
    {
        return $ratio->isGreaterThanOrEqualTo($this->safeLevel);
    }

    public function requiresMarginCall(BigDecimal $ratio): bool
    {
        return $ratio->isLessThan($this->marginCallLevel);
    }

    public function requiresLiquidation(BigDecimal $ratio): bool
    {
        return $ratio->isLessThan($this->liquidationLevel);
    }

    public function toString(): string
    {
        return sprintf(
            'Liquidation: %.1f%%, Margin Call: %.1f%%, Safe: %.1f%%',
            $this->liquidationPercentage(),
            $this->marginCallPercentage(),
            $this->safePercentage()
        );
    }
}
