<?php

declare(strict_types=1);

namespace App\Domain\Shared\CQRS;

/**
 * Base interface for all commands in the CQRS pattern.
 */
interface Command
{
    /**
     * Get a unique identifier for this command instance.
     */
    public function getCommandId(): string;

    /**
     * Get the timestamp when this command was created.
     */
    public function getTimestamp(): \DateTimeInterface;

    /**
     * Get metadata associated with this command.
     */
    public function getMetadata(): array;

    /**
     * Convert the command to an array for serialization.
     */
    public function toArray(): array;
}
