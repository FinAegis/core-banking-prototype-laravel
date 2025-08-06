<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

/**
 * Domain Event Bus interface for decoupled event publishing and handling.
 */
interface DomainEventBus
{
    /**
     * Publish a domain event.
     *
     * @param DomainEvent $event The event to publish
     */
    public function publish(DomainEvent $event): void;

    /**
     * Publish multiple events.
     *
     * @param array<DomainEvent> $events The events to publish
     */
    public function publishMultiple(array $events): void;

    /**
     * Subscribe to a domain event.
     *
     * @param string $eventClass The fully qualified class name of the event
     * @param callable|string $handler The handler callable or class
     * @param int $priority Handler priority (higher executes first)
     */
    public function subscribe(string $eventClass, callable|string $handler, int $priority = 0): void;

    /**
     * Unsubscribe from a domain event.
     *
     * @param string $eventClass The event class
     * @param callable|string $handler The handler to remove
     */
    public function unsubscribe(string $eventClass, callable|string $handler): void;

    /**
     * Publish an event asynchronously.
     *
     * @param DomainEvent $event The event to publish
     * @param int $delay Delay in seconds before processing
     */
    public function publishAsync(DomainEvent $event, int $delay = 0): void;

    /**
     * Record events for later publishing (useful in transactions).
     *
     * @param DomainEvent $event The event to record
     */
    public function record(DomainEvent $event): void;

    /**
     * Dispatch all recorded events.
     */
    public function dispatchRecorded(): void;

    /**
     * Clear recorded events without dispatching.
     */
    public function clearRecorded(): void;

    /**
     * Get all subscribers for a specific event.
     *
     * @param string $eventClass The event class
     * @return array List of subscribers
     */
    public function getSubscribers(string $eventClass): array;
}
