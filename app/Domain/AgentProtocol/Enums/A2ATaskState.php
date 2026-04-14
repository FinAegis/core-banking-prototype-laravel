<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Enums;

/**
 * A2A Task state machine per the Agent-to-Agent protocol specification.
 *
 * Terminal states: completed, canceled, failed.
 * Valid transitions are strictly enforced.
 */
enum A2ATaskState: string
{
    case SUBMITTED = 'submitted';
    case WORKING = 'working';
    case INPUT_REQUIRED = 'input-required';
    case COMPLETED = 'completed';
    case CANCELED = 'canceled';
    case FAILED = 'failed';

    /**
     * Returns true for terminal states (completed, canceled, failed).
     * No further transitions are allowed from terminal states.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::CANCELED, self::FAILED => true,
            default                                       => false,
        };
    }

    /**
     * Returns the list of states this state may validly transition to.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::SUBMITTED => [
                self::WORKING,
                self::CANCELED,
                self::FAILED,
            ],
            self::WORKING => [
                self::INPUT_REQUIRED,
                self::COMPLETED,
                self::CANCELED,
                self::FAILED,
            ],
            self::INPUT_REQUIRED => [
                self::WORKING,
                self::CANCELED,
                self::FAILED,
            ],
            self::COMPLETED, self::CANCELED, self::FAILED => [],
        };
    }

    /**
     * Returns true when transitioning to $target is a valid move from this state.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
