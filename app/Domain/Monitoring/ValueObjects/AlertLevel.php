<?php

declare(strict_types=1);

namespace App\Domain\Monitoring\ValueObjects;

enum AlertLevel: string
{
    case INFO = 'info';
    case WARNING = 'warning';
    case CRITICAL = 'critical';
    case EMERGENCY = 'emergency';

    public function priority(): int
    {
        return match ($this) {
            self::INFO      => 1,
            self::WARNING   => 2,
            self::CRITICAL  => 3,
            self::EMERGENCY => 4,
        };
    }

    public function requiresImmediateAction(): bool
    {
        return in_array($this, [self::CRITICAL, self::EMERGENCY]);
    }

    public function color(): string
    {
        return match ($this) {
            self::INFO      => '#3498db',
            self::WARNING   => '#f39c12',
            self::CRITICAL  => '#e74c3c',
            self::EMERGENCY => '#c0392b',
        };
    }
}
