<?php

declare(strict_types=1);

namespace App\Domain\Treasury\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing liquidity metrics.
 */
final class LiquidityMetrics
{
    public function __construct(
        public readonly float $liquidityCoverageRatio,
        public readonly float $netStableFundingRatio,
        public readonly int $stressTestSurvivalDays,
        public readonly float $probabilityOfShortage,
        public readonly float $valueAtRisk95,
        public readonly float $expectedShortfall,
        public readonly float $liquidityBufferAdequacy
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        if ($this->liquidityCoverageRatio < 0) {
            throw new InvalidArgumentException('Liquidity Coverage Ratio cannot be negative');
        }

        if ($this->netStableFundingRatio < 0) {
            throw new InvalidArgumentException('Net Stable Funding Ratio cannot be negative');
        }

        if ($this->stressTestSurvivalDays < 0) {
            throw new InvalidArgumentException('Stress Test Survival Days cannot be negative');
        }

        if ($this->probabilityOfShortage < 0 || $this->probabilityOfShortage > 1) {
            throw new InvalidArgumentException('Probability of Shortage must be between 0 and 1');
        }

        if ($this->valueAtRisk95 < 0) {
            throw new InvalidArgumentException('Value at Risk cannot be negative');
        }

        if ($this->expectedShortfall < 0) {
            throw new InvalidArgumentException('Expected Shortfall cannot be negative');
        }

        if ($this->liquidityBufferAdequacy < 0 || $this->liquidityBufferAdequacy > 1) {
            throw new InvalidArgumentException('Liquidity Buffer Adequacy must be between 0 and 1');
        }
    }

    public function isHealthy(): bool
    {
        return $this->liquidityCoverageRatio >= 1.0
            && $this->netStableFundingRatio >= 1.0
            && $this->stressTestSurvivalDays >= 30
            && $this->probabilityOfShortage <= 0.05;
    }

    public function getRiskLevel(): string
    {
        if (! $this->isHealthy()) {
            return 'high';
        }

        if ($this->liquidityCoverageRatio < 1.2 || $this->stressTestSurvivalDays < 45) {
            return 'medium';
        }

        return 'low';
    }

    public function toArray(): array
    {
        return [
            'liquidity_coverage_ratio'  => $this->liquidityCoverageRatio,
            'net_stable_funding_ratio'  => $this->netStableFundingRatio,
            'stress_test_survival_days' => $this->stressTestSurvivalDays,
            'probability_of_shortage'   => $this->probabilityOfShortage,
            'value_at_risk_95'          => $this->valueAtRisk95,
            'expected_shortfall'        => $this->expectedShortfall,
            'liquidity_buffer_adequacy' => $this->liquidityBufferAdequacy,
            'is_healthy'                => $this->isHealthy(),
            'risk_level'                => $this->getRiskLevel(),
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            liquidityCoverageRatio: (float) ($data['liquidity_coverage_ratio'] ?? 0),
            netStableFundingRatio: (float) ($data['net_stable_funding_ratio'] ?? 0),
            stressTestSurvivalDays: (int) ($data['stress_test_survival_days'] ?? 0),
            probabilityOfShortage: (float) ($data['probability_of_shortage'] ?? 0),
            valueAtRisk95: (float) ($data['value_at_risk_95'] ?? 0),
            expectedShortfall: (float) ($data['expected_shortfall'] ?? 0),
            liquidityBufferAdequacy: (float) ($data['liquidity_buffer_adequacy'] ?? 0)
        );
    }
}
