<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Enums;

/**
 * A2A Message Priority Levels.
 */
enum MessagePriority: int
{
    case CRITICAL = 100;
    case HIGH = 75;
    case NORMAL = 50;
    case LOW = 25;
    case BACKGROUND = 10;

    /**
     * Get the queue name for this priority level.
     */
    public function getQueueName(): string
    {
        return match ($this) {
            self::CRITICAL   => 'agent-messages-critical',
            self::HIGH       => 'agent-messages-high',
            self::NORMAL     => 'agent-messages',
            self::LOW        => 'agent-messages-low',
            self::BACKGROUND => 'agent-messages-background',
        };
    }

    /**
     * Get the maximum processing time allowed for this priority.
     */
    public function getMaxProcessingTime(): int
    {
        return match ($this) {
            self::CRITICAL   => 5,
            self::HIGH       => 15,
            self::NORMAL     => 30,
            self::LOW        => 60,
            self::BACKGROUND => 300,
        };
    }

    /**
     * Get the retry delay multiplier for this priority.
     */
    public function getRetryDelayMultiplier(): float
    {
        return match ($this) {
            self::CRITICAL   => 0.5,
            self::HIGH       => 1.0,
            self::NORMAL     => 1.5,
            self::LOW        => 2.0,
            self::BACKGROUND => 3.0,
        };
    }

    /**
     * Get the maximum number of retries for this priority.
     */
    public function getMaxRetries(): int
    {
        return match ($this) {
            self::CRITICAL   => 5,
            self::HIGH       => 4,
            self::NORMAL     => 3,
            self::LOW        => 2,
            self::BACKGROUND => 1,
        };
    }

    /**
     * Create from numeric value (for backwards compatibility).
     */
    public static function fromNumeric(int $value): self
    {
        return match (true) {
            $value >= 90 => self::CRITICAL,
            $value >= 70 => self::HIGH,
            $value >= 40 => self::NORMAL,
            $value >= 20 => self::LOW,
            default      => self::BACKGROUND,
        };
    }
}
