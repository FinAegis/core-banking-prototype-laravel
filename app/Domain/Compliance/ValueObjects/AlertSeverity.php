<?php

declare(strict_types=1);

namespace App\Domain\Compliance\ValueObjects;

use InvalidArgumentException;

class AlertSeverity
{
    private const VALID_SEVERITIES = [
        'low',
        'medium',
        'high',
        'critical',
    ];

    private const PRIORITY_MAP = [
        'low'      => 1,
        'medium'   => 2,
        'high'     => 3,
        'critical' => 4,
    ];

    private string $value;

    public function __construct(string $value)
    {
        $value = strtolower($value);

        if (! in_array($value, self::VALID_SEVERITIES, true)) {
            throw new InvalidArgumentException("Invalid alert severity: {$value}");
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function priority(): int
    {
        return self::PRIORITY_MAP[$this->value];
    }

    public function isHigherThan(self $other): bool
    {
        return $this->priority() > $other->priority();
    }

    public function isLowerThan(self $other): bool
    {
        return $this->priority() < $other->priority();
    }

    public function isCritical(): bool
    {
        return $this->value === 'critical';
    }

    public function isHigh(): bool
    {
        return $this->value === 'high';
    }

    public function requiresImmediateAction(): bool
    {
        return in_array($this->value, ['critical', 'high'], true);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
