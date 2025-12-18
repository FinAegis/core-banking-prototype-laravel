<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Enums;

/**
 * A2A Message Status tracking.
 */
enum MessageStatus: string
{
    case PENDING = 'pending';
    case QUEUED = 'queued';
    case ROUTING = 'routing';
    case DELIVERING = 'delivering';
    case DELIVERED = 'delivered';
    case ACKNOWLEDGED = 'acknowledged';
    case FAILED = 'failed';
    case RETRYING = 'retrying';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';
    case COMPENSATING = 'compensating';
    case COMPENSATED = 'compensated';

    /**
     * Check if this is a terminal status.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::ACKNOWLEDGED,
            self::FAILED,
            self::EXPIRED,
            self::CANCELLED,
            self::COMPENSATED => true,
            default           => false,
        };
    }

    /**
     * Check if this status indicates success.
     */
    public function isSuccessful(): bool
    {
        return match ($this) {
            self::DELIVERED,
            self::ACKNOWLEDGED,
            self::COMPENSATED => true,
            default           => false,
        };
    }

    /**
     * Check if message can be retried from this status.
     */
    public function canRetry(): bool
    {
        return match ($this) {
            self::FAILED,
            self::EXPIRED => true,
            default       => false,
        };
    }

    /**
     * Get the next logical status in the workflow.
     */
    public function getNextStatus(): ?self
    {
        return match ($this) {
            self::PENDING    => self::QUEUED,
            self::QUEUED     => self::ROUTING,
            self::ROUTING    => self::DELIVERING,
            self::DELIVERING => self::DELIVERED,
            self::DELIVERED  => self::ACKNOWLEDGED,
            default          => null,
        };
    }
}
