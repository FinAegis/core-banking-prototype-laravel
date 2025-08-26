<?php

declare(strict_types=1);

namespace App\Domain\Treasury\ValueObjects;

use InvalidArgumentException;

final class InvestmentStrategy
{
    public const RISK_CONSERVATIVE = 'conservative';

    public const RISK_MODERATE = 'moderate';

    public const RISK_AGGRESSIVE = 'aggressive';

    public const RISK_SPECULATIVE = 'speculative';

    private const VALID_RISK_PROFILES = [
        self::RISK_CONSERVATIVE,
        self::RISK_MODERATE,
        self::RISK_AGGRESSIVE,
        self::RISK_SPECULATIVE,
    ];

    private string $riskProfile;

    private float $rebalanceThreshold;

    private float $targetReturn;

    private array $constraints;

    private array $metadata;

    public function __construct(
        string $riskProfile,
        float $rebalanceThreshold,
        float $targetReturn,
        array $constraints = [],
        array $metadata = []
    ) {
        if (! in_array($riskProfile, self::VALID_RISK_PROFILES, true)) {
            throw new InvalidArgumentException("Invalid risk profile: {$riskProfile}");
        }

        if ($rebalanceThreshold < 0.0 || $rebalanceThreshold > 50.0) {
            throw new InvalidArgumentException('Rebalance threshold must be between 0 and 50%');
        }

        if ($targetReturn < 0.0) {
            throw new InvalidArgumentException('Target return cannot be negative');
        }

        $this->riskProfile = $riskProfile;
        $this->rebalanceThreshold = $rebalanceThreshold;
        $this->targetReturn = $targetReturn;
        $this->constraints = $constraints;
        $this->metadata = $metadata;
    }

    public function getRiskProfile(): string
    {
        return $this->riskProfile;
    }

    public function getRebalanceThreshold(): float
    {
        return $this->rebalanceThreshold;
    }

    public function getTargetReturn(): float
    {
        return $this->targetReturn;
    }

    public function getConstraints(): array
    {
        return $this->constraints;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function isConservative(): bool
    {
        return $this->riskProfile === self::RISK_CONSERVATIVE;
    }

    public function isModerate(): bool
    {
        return $this->riskProfile === self::RISK_MODERATE;
    }

    public function isAggressive(): bool
    {
        return $this->riskProfile === self::RISK_AGGRESSIVE;
    }

    public function isSpeculative(): bool
    {
        return $this->riskProfile === self::RISK_SPECULATIVE;
    }

    public function isValid(): bool
    {
        return in_array($this->riskProfile, self::VALID_RISK_PROFILES, true) &&
               $this->rebalanceThreshold >= 0.0 &&
               $this->rebalanceThreshold <= 50.0 &&
               $this->targetReturn >= 0.0;
    }

    public function getDefaultAssetClasses(): array
    {
        return match ($this->riskProfile) {
            self::RISK_CONSERVATIVE => [
                'cash'     => 30.0,
                'bonds'    => 60.0,
                'equities' => 10.0,
            ],
            self::RISK_MODERATE => [
                'cash'         => 10.0,
                'bonds'        => 40.0,
                'equities'     => 45.0,
                'alternatives' => 5.0,
            ],
            self::RISK_AGGRESSIVE => [
                'cash'         => 5.0,
                'bonds'        => 25.0,
                'equities'     => 60.0,
                'alternatives' => 10.0,
            ],
            self::RISK_SPECULATIVE => [
                'cash'         => 5.0,
                'bonds'        => 15.0,
                'equities'     => 60.0,
                'alternatives' => 20.0,
            ],
            default => [
                'cash' => 100.0,
            ],
        };
    }

    public function getExpectedVolatility(): float
    {
        return match ($this->riskProfile) {
            self::RISK_CONSERVATIVE => 0.05,  // 5% volatility
            self::RISK_MODERATE     => 0.12,      // 12% volatility
            self::RISK_AGGRESSIVE   => 0.20,    // 20% volatility
            self::RISK_SPECULATIVE  => 0.30,   // 30% volatility
            default                 => 0.00,
        };
    }

    public function updateRebalanceThreshold(float $threshold): self
    {
        return new self(
            $this->riskProfile,
            $threshold,
            $this->targetReturn,
            $this->constraints,
            $this->metadata
        );
    }

    public function updateTargetReturn(float $targetReturn): self
    {
        return new self(
            $this->riskProfile,
            $this->rebalanceThreshold,
            $targetReturn,
            $this->constraints,
            $this->metadata
        );
    }

    public function withConstraint(string $key, mixed $value): self
    {
        $constraints = $this->constraints;
        $constraints[$key] = $value;

        return new self(
            $this->riskProfile,
            $this->rebalanceThreshold,
            $this->targetReturn,
            $constraints,
            $this->metadata
        );
    }

    public function toArray(): array
    {
        return [
            'riskProfile'        => $this->riskProfile,
            'rebalanceThreshold' => $this->rebalanceThreshold,
            'targetReturn'       => $this->targetReturn,
            'constraints'        => $this->constraints,
            'metadata'           => $this->metadata,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['riskProfile'],
            $data['rebalanceThreshold'],
            $data['targetReturn'],
            $data['constraints'] ?? [],
            $data['metadata'] ?? []
        );
    }

    public function equals(self $other): bool
    {
        return $this->riskProfile === $other->riskProfile &&
               abs($this->rebalanceThreshold - $other->rebalanceThreshold) < 0.01 &&
               abs($this->targetReturn - $other->targetReturn) < 0.01 &&
               $this->constraints === $other->constraints;
    }

    public function __toString(): string
    {
        return sprintf(
            '%s risk (%.1f%% rebalance threshold, %.2f%% target return)',
            ucfirst($this->riskProfile),
            $this->rebalanceThreshold,
            $this->targetReturn * 100
        );
    }
}
