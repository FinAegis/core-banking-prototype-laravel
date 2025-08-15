<?php

declare(strict_types=1);

namespace App\Domain\Treasury\ValueObjects;

use InvalidArgumentException;

final class RiskProfile
{
    public const LOW = 'low';

    public const MEDIUM = 'medium';

    public const HIGH = 'high';

    public const VERY_HIGH = 'very_high';

    private const VALID_PROFILES = [
        self::LOW,
        self::MEDIUM,
        self::HIGH,
        self::VERY_HIGH,
    ];

    private string $level;

    private float $score;

    private array $factors;

    public function __construct(string $level, float $score, array $factors = [])
    {
        if (! in_array($level, self::VALID_PROFILES, true)) {
            throw new InvalidArgumentException("Invalid risk level: {$level}");
        }

        if ($score < 0 || $score > 100) {
            throw new InvalidArgumentException('Risk score must be between 0 and 100');
        }

        $this->level = $level;
        $this->score = $score;
        $this->factors = $factors;
    }

    public static function fromScore(float $score, array $factors = []): self
    {
        $level = match (true) {
            $score <= 25 => self::LOW,
            $score <= 50 => self::MEDIUM,
            $score <= 75 => self::HIGH,
            default      => self::VERY_HIGH,
        };

        return new self($level, $score, $factors);
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function getFactors(): array
    {
        return $this->factors;
    }

    public function getMaxExposure(): float
    {
        return match ($this->level) {
            self::LOW       => 0.10,      // 10% max exposure
            self::MEDIUM    => 0.25,   // 25% max exposure
            self::HIGH      => 0.50,     // 50% max exposure
            self::VERY_HIGH => 0.75, // 75% max exposure
            default         => 0.25,   // Default to medium exposure
        };
    }

    public function getRequiredLiquidity(): float
    {
        return match ($this->level) {
            self::LOW       => 0.50,      // 50% liquidity required
            self::MEDIUM    => 0.35,   // 35% liquidity required
            self::HIGH      => 0.20,     // 20% liquidity required
            self::VERY_HIGH => 0.10, // 10% liquidity required
            default         => 0.35,   // Default to medium liquidity
        };
    }

    public function isAcceptable(): bool
    {
        return $this->score <= 75; // Threshold for acceptable risk
    }

    public function requiresApproval(): bool
    {
        return $this->level === self::HIGH || $this->level === self::VERY_HIGH;
    }

    public function equals(self $other): bool
    {
        return $this->level === $other->level &&
               abs($this->score - $other->score) < 0.01;
    }

    public function __toString(): string
    {
        return sprintf('%s (%.2f)', $this->level, $this->score);
    }
}
