<?php

declare(strict_types=1);

namespace App\Domain\Treasury\ValueObjects;

use Carbon\Carbon;

/**
 * Value object representing a cash flow projection.
 */
final class CashFlowProjection
{
    public function __construct(
        public readonly Carbon $date,
        public readonly int $dayNumber,
        public readonly float $projectedInflow,
        public readonly float $projectedOutflow,
        public readonly float $netFlow,
        public readonly float $projectedBalance,
        public readonly array $confidenceInterval
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->dayNumber < 1) {
            throw new \InvalidArgumentException('Day number must be positive');
        }

        if ($this->projectedInflow < 0) {
            throw new \InvalidArgumentException('Projected inflow cannot be negative');
        }

        if ($this->projectedOutflow < 0) {
            throw new \InvalidArgumentException('Projected outflow cannot be negative');
        }

        if (! isset($this->confidenceInterval['lower']) || ! isset($this->confidenceInterval['upper'])) {
            throw new \InvalidArgumentException('Confidence interval must have lower and upper bounds');
        }

        if ($this->confidenceInterval['lower'] > $this->confidenceInterval['upper']) {
            throw new \InvalidArgumentException('Lower confidence bound cannot exceed upper bound');
        }
    }

    public function isNegative(): bool
    {
        return $this->projectedBalance < 0;
    }

    public function isWithinConfidence(float $actualValue): bool
    {
        return $actualValue >= $this->confidenceInterval['lower']
            && $actualValue <= $this->confidenceInterval['upper'];
    }

    public function getConfidenceRange(): float
    {
        return $this->confidenceInterval['upper'] - $this->confidenceInterval['lower'];
    }

    public function getNetMargin(): float
    {
        $total = $this->projectedInflow + $this->projectedOutflow;

        return $total > 0 ? $this->netFlow / $total : 0;
    }

    public function toArray(): array
    {
        return [
            'date'                => $this->date->format('Y-m-d'),
            'day_number'          => $this->dayNumber,
            'projected_inflow'    => round($this->projectedInflow, 2),
            'projected_outflow'   => round($this->projectedOutflow, 2),
            'net_flow'            => round($this->netFlow, 2),
            'projected_balance'   => round($this->projectedBalance, 2),
            'confidence_interval' => [
                'lower' => round($this->confidenceInterval['lower'], 2),
                'upper' => round($this->confidenceInterval['upper'], 2),
            ],
            'is_negative'      => $this->isNegative(),
            'confidence_range' => round($this->getConfidenceRange(), 2),
            'net_margin'       => round($this->getNetMargin(), 4),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            date: Carbon::parse($data['date']),
            dayNumber: (int) $data['day_number'],
            projectedInflow: (float) $data['projected_inflow'],
            projectedOutflow: (float) $data['projected_outflow'],
            netFlow: (float) $data['net_flow'],
            projectedBalance: (float) $data['projected_balance'],
            confidenceInterval: $data['confidence_interval']
        );
    }
}
