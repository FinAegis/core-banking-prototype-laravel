<?php

declare(strict_types=1);

namespace App\Domain\Performance\ValueObjects;

use InvalidArgumentException;

class PerformanceThreshold
{
    public function __construct(
        private float $value,
        private string $operator = '>',
        private string $severity = 'warning',
        private bool $triggerAlert = true,
        private int $consecutiveBreaches = 1,
        private int $cooldownMinutes = 5
    ) {
        $this->validateOperator();
        $this->validateSeverity();
    }

    private function validateOperator(): void
    {
        $validOperators = ['>', '>=', '<', '<=', '==', '!='];
        if (! in_array($this->operator, $validOperators, true)) {
            throw new InvalidArgumentException("Invalid operator: {$this->operator}");
        }
    }

    private function validateSeverity(): void
    {
        $validSeverities = ['info', 'warning', 'critical', 'emergency'];
        if (! in_array($this->severity, $validSeverities, true)) {
            throw new InvalidArgumentException("Invalid severity: {$this->severity}");
        }
    }

    public function isExceeded(float $value): bool
    {
        return match ($this->operator) {
            '>'     => $value > $this->value,
            '>='    => $value >= $this->value,
            '<'     => $value < $this->value,
            '<='    => $value <= $this->value,
            '=='    => abs($value - $this->value) < 0.0001,
            '!='    => abs($value - $this->value) >= 0.0001,
            default => false,
        };
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function getOperator(): string
    {
        return $this->operator;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function shouldTriggerAlert(): bool
    {
        return $this->triggerAlert;
    }

    public function getConsecutiveBreaches(): int
    {
        return $this->consecutiveBreaches;
    }

    public function getCooldownMinutes(): int
    {
        return $this->cooldownMinutes;
    }

    public function toArray(): array
    {
        return [
            'value'               => $this->value,
            'operator'            => $this->operator,
            'severity'            => $this->severity,
            'triggerAlert'        => $this->triggerAlert,
            'consecutiveBreaches' => $this->consecutiveBreaches,
            'cooldownMinutes'     => $this->cooldownMinutes,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            value: $data['value'],
            operator: $data['operator'] ?? '>',
            severity: $data['severity'] ?? 'warning',
            triggerAlert: $data['triggerAlert'] ?? true,
            consecutiveBreaches: $data['consecutiveBreaches'] ?? 1,
            cooldownMinutes: $data['cooldownMinutes'] ?? 5
        );
    }
}
