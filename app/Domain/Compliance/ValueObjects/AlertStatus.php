<?php

declare(strict_types=1);

namespace App\Domain\Compliance\ValueObjects;

use InvalidArgumentException;

class AlertStatus
{
    private const VALID_STATUSES = [
        'open',
        'in_review',
        'investigating',
        'escalated',
        'resolved',
        'closed',
        'false_positive',
    ];

    private string $value;

    public function __construct(string $value)
    {
        if (! in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException("Invalid alert status: {$value}");
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isOpen(): bool
    {
        return $this->value === 'open';
    }

    public function isInvestigating(): bool
    {
        return $this->value === 'investigating';
    }

    public function isEscalated(): bool
    {
        return $this->value === 'escalated';
    }

    public function isResolved(): bool
    {
        return $this->value === 'resolved';
    }

    public function isClosed(): bool
    {
        return in_array($this->value, ['closed', 'resolved', 'false_positive'], true);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        $transitions = [
            'open'           => ['investigating', 'escalated', 'resolved', 'false_positive'],
            'investigating'  => ['escalated', 'resolved', 'false_positive', 'closed'],
            'escalated'      => ['resolved', 'closed'],
            'resolved'       => ['closed'],
            'closed'         => [],
            'false_positive' => ['closed'],
        ];

        return in_array($newStatus, $transitions[$this->value] ?? [], true);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
