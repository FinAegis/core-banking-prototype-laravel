<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\ValueObjects;

use Brick\Math\BigDecimal;

final class PositionHealth
{
    private BigDecimal $ratio;

    private bool $isHealthy;

    private bool $requiresAction;

    public function __construct(
        BigDecimal $ratio,
        bool $isHealthy,
        bool $requiresAction
    ) {
        $this->ratio = $ratio;
        $this->isHealthy = $isHealthy;
        $this->requiresAction = $requiresAction;
    }

    public static function calculate(
        BigDecimal $collateralValue,
        BigDecimal $debtAmount,
        LiquidationThreshold $threshold
    ): self {
        if ($debtAmount->isZero()) {
            return new self(
                BigDecimal::of('999'), // Max ratio for no debt
                true,
                false
            );
        }

        $ratio = $collateralValue->dividedBy($debtAmount, 4);
        $isHealthy = $ratio->isGreaterThanOrEqualTo($threshold->safeLevel());
        $requiresAction = $ratio->isLessThan($threshold->marginCallLevel());

        return new self($ratio, $isHealthy, $requiresAction);
    }

    public function ratio(): BigDecimal
    {
        return $this->ratio;
    }

    public function ratioPercentage(): float
    {
        return $this->ratio->multipliedBy(100)->toFloat();
    }

    public function isHealthy(): bool
    {
        return $this->isHealthy;
    }

    public function requiresAction(): bool
    {
        return $this->requiresAction;
    }

    public function requiresMarginCall(): bool
    {
        return $this->requiresAction && ! $this->requiresLiquidation();
    }

    public function requiresLiquidation(): bool
    {
        // Liquidation if ratio < 1.0 (100%)
        return $this->ratio->isLessThan(BigDecimal::one());
    }

    public function status(): string
    {
        if ($this->requiresLiquidation()) {
            return 'LIQUIDATION';
        }

        if ($this->requiresMarginCall()) {
            return 'MARGIN_CALL';
        }

        if (! $this->isHealthy) {
            return 'AT_RISK';
        }

        return 'HEALTHY';
    }

    public function statusColor(): string
    {
        return match ($this->status()) {
            'HEALTHY'     => 'green',
            'AT_RISK'     => 'yellow',
            'MARGIN_CALL' => 'orange',
            'LIQUIDATION' => 'red',
            default       => 'gray'
        };
    }

    public function toString(): string
    {
        return sprintf(
            '%s (%.2f%%)',
            $this->status(),
            $this->ratioPercentage()
        );
    }
}
