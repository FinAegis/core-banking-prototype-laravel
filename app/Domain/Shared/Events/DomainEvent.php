<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

use DateTimeInterface;

/**
 * Base interface for all domain events.
 */
interface DomainEvent
{
    /**
     * Get the aggregate ID this event belongs to.
     */
    public function getAggregateId(): string;

    /**
     * Get the event ID.
     */
    public function getEventId(): string;

    /**
     * Get the event name.
     */
    public function getEventName(): string;

    /**
     * Get when the event occurred.
     */
    public function getOccurredAt(): DateTimeInterface;

    /**
     * Get the event version for schema evolution.
     */
    public function getVersion(): int;

    /**
     * Get event metadata.
     */
    public function getMetadata(): array;

    /**
     * Get the event payload.
     */
    public function getPayload(): array;

    /**
     * Convert the event to an array for serialization.
     */
    public function toArray(): array;

    /**
     * Create an event from an array.
     */
    public static function fromArray(array $data): self;
}
