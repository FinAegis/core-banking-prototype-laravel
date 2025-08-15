<?php

declare(strict_types=1);

namespace App\Domain\Treasury\ValueObjects;

use InvalidArgumentException;

final class AllocationStrategy
{
    public const CONSERVATIVE = 'conservative';

    public const BALANCED = 'balanced';

    public const AGGRESSIVE = 'aggressive';

    public const CUSTOM = 'custom';

    private const VALID_STRATEGIES = [
        self::CONSERVATIVE,
        self::BALANCED,
        self::AGGRESSIVE,
        self::CUSTOM,
    ];

    private string $value;

    private array $allocations;

    public function __construct(string $strategy, array $allocations = [])
    {
        if (! in_array($strategy, self::VALID_STRATEGIES, true)) {
            throw new InvalidArgumentException("Invalid allocation strategy: {$strategy}");
        }

        $this->value = $strategy;
        $this->allocations = $this->validateAllocations($allocations);
    }

    private function validateAllocations(array $allocations): array
    {
        if ($this->value === self::CUSTOM && empty($allocations)) {
            throw new InvalidArgumentException('Custom strategy requires allocation details');
        }

        if (! empty($allocations)) {
            $total = array_sum(array_column($allocations, 'percentage'));
            if (abs($total - 100.0) > 0.01) {
                throw new InvalidArgumentException('Allocations must sum to 100%');
            }
        }

        return $allocations;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getAllocations(): array
    {
        return $this->allocations;
    }

    public function getDefaultAllocations(): array
    {
        return match ($this->value) {
            self::CONSERVATIVE => [
                ['type' => 'cash', 'percentage' => 40.0],
                ['type' => 'bonds', 'percentage' => 50.0],
                ['type' => 'equities', 'percentage' => 10.0],
            ],
            self::BALANCED => [
                ['type' => 'cash', 'percentage' => 20.0],
                ['type' => 'bonds', 'percentage' => 40.0],
                ['type' => 'equities', 'percentage' => 40.0],
            ],
            self::AGGRESSIVE => [
                ['type' => 'cash', 'percentage' => 10.0],
                ['type' => 'bonds', 'percentage' => 20.0],
                ['type' => 'equities', 'percentage' => 70.0],
            ],
            self::CUSTOM => $this->allocations,
            default      => [
                ['type' => 'cash', 'percentage' => 100.0],
            ],
        };
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value &&
               $this->allocations === $other->allocations;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
