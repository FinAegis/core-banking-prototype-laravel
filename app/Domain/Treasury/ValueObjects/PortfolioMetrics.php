<?php

declare(strict_types=1);

namespace App\Domain\Treasury\ValueObjects;

use InvalidArgumentException;

final class PortfolioMetrics
{
    private float $totalValue;

    private float $returns;

    private float $sharpeRatio;

    private float $volatility;

    private float $maxDrawdown;

    private float $alpha;

    private float $beta;

    private array $additionalMetrics;

    public function __construct(
        float $totalValue,
        float $returns,
        float $sharpeRatio,
        float $volatility,
        float $maxDrawdown = 0.0,
        float $alpha = 0.0,
        float $beta = 1.0,
        array $additionalMetrics = []
    ) {
        if ($totalValue < 0.0) {
            throw new InvalidArgumentException('Total value cannot be negative');
        }

        if ($volatility < 0.0) {
            throw new InvalidArgumentException('Volatility cannot be negative');
        }

        if ($maxDrawdown > 0.0) {
            throw new InvalidArgumentException('Max drawdown should be negative or zero');
        }

        $this->totalValue = $totalValue;
        $this->returns = $returns;
        $this->sharpeRatio = $sharpeRatio;
        $this->volatility = $volatility;
        $this->maxDrawdown = $maxDrawdown;
        $this->alpha = $alpha;
        $this->beta = $beta;
        $this->additionalMetrics = $additionalMetrics;
    }

    public function getTotalValue(): float
    {
        return $this->totalValue;
    }

    public function getReturns(): float
    {
        return $this->returns;
    }

    public function getSharpeRatio(): float
    {
        return $this->sharpeRatio;
    }

    public function getVolatility(): float
    {
        return $this->volatility;
    }

    public function getMaxDrawdown(): float
    {
        return $this->maxDrawdown;
    }

    public function getAlpha(): float
    {
        return $this->alpha;
    }

    public function getBeta(): float
    {
        return $this->beta;
    }

    public function getAdditionalMetrics(): array
    {
        return $this->additionalMetrics;
    }

    public function getAdditionalMetric(string $key): mixed
    {
        return $this->additionalMetrics[$key] ?? null;
    }

    public function isValid(): bool
    {
        return $this->totalValue >= 0.0 &&
               $this->volatility >= 0.0 &&
               $this->maxDrawdown <= 0.0;
    }

    public function isPositiveReturn(): bool
    {
        return $this->returns > 0.0;
    }

    public function isHighVolatility(float $threshold = 0.15): bool
    {
        return $this->volatility > $threshold;
    }

    public function isHighSharpeRatio(float $threshold = 1.0): bool
    {
        return $this->sharpeRatio > $threshold;
    }

    public function hasSignificantDrawdown(float $threshold = -0.10): bool
    {
        return $this->maxDrawdown < $threshold;
    }

    public function getRiskAdjustedReturn(): float
    {
        return $this->volatility > 0.0 ? $this->returns / $this->volatility : 0.0;
    }

    public function getInformationRatio(): float
    {
        // Simplified information ratio calculation
        return $this->volatility > 0.0 ? $this->alpha / $this->volatility : 0.0;
    }

    public function withAdditionalMetric(string $key, mixed $value): self
    {
        $metrics = $this->additionalMetrics;
        $metrics[$key] = $value;

        return new self(
            $this->totalValue,
            $this->returns,
            $this->sharpeRatio,
            $this->volatility,
            $this->maxDrawdown,
            $this->alpha,
            $this->beta,
            $metrics
        );
    }

    public function updateTotalValue(float $totalValue): self
    {
        return new self(
            $totalValue,
            $this->returns,
            $this->sharpeRatio,
            $this->volatility,
            $this->maxDrawdown,
            $this->alpha,
            $this->beta,
            $this->additionalMetrics
        );
    }

    public function toArray(): array
    {
        return [
            'totalValue'        => $this->totalValue,
            'returns'           => $this->returns,
            'sharpeRatio'       => $this->sharpeRatio,
            'volatility'        => $this->volatility,
            'maxDrawdown'       => $this->maxDrawdown,
            'alpha'             => $this->alpha,
            'beta'              => $this->beta,
            'additionalMetrics' => $this->additionalMetrics,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['totalValue'],
            $data['returns'],
            $data['sharpeRatio'],
            $data['volatility'],
            $data['maxDrawdown'] ?? 0.0,
            $data['alpha'] ?? 0.0,
            $data['beta'] ?? 1.0,
            $data['additionalMetrics'] ?? []
        );
    }

    public function equals(self $other): bool
    {
        return abs($this->totalValue - $other->totalValue) < 0.01 &&
               abs($this->returns - $other->returns) < 0.01 &&
               abs($this->sharpeRatio - $other->sharpeRatio) < 0.01 &&
               abs($this->volatility - $other->volatility) < 0.01;
    }

    public function __toString(): string
    {
        return sprintf(
            'Portfolio: $%.2f, Returns: %.2f%%, Sharpe: %.2f, Volatility: %.2f%%',
            $this->totalValue,
            $this->returns * 100,
            $this->sharpeRatio,
            $this->volatility * 100
        );
    }
}
