<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Domain\Shared\Events\DomainEventBus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;

/**
 * Laravel implementation of the Domain Event Bus.
 * Bridges domain events with Laravel's event system.
 */
class LaravelDomainEventBus implements DomainEventBus
{
    /**
     * Registered event handlers with priorities.
     *
     * @var array<string, array>
     */
    private array $handlers = [];

    /**
     * Recorded events for transactional publishing.
     *
     * @var array<DomainEvent>
     */
    private array $recordedEvents = [];

    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly Container $container
    ) {
    }

    /**
     * Publish a domain event.
     */
    public function publish(DomainEvent $event): void
    {
        $eventClass = get_class($event);

        // First, dispatch to Laravel's event system for compatibility
        $this->dispatcher->dispatch($event);

        // Then, dispatch to registered domain handlers
        if (isset($this->handlers[$eventClass])) {
            $handlers = $this->sortHandlersByPriority($this->handlers[$eventClass]);

            foreach ($handlers as $handler) {
                $this->invokeHandler($handler['handler'], $event);
            }
        }
    }

    /**
     * Publish multiple events.
     */
    public function publishMultiple(array $events): void
    {
        foreach ($events as $event) {
            if (! $event instanceof DomainEvent) {
                throw new InvalidArgumentException('All items must be DomainEvent instances');
            }

            $this->publish($event);
        }
    }

    /**
     * Subscribe to a domain event.
     */
    public function subscribe(string $eventClass, callable|string $handler, int $priority = 0): void
    {
        if (! isset($this->handlers[$eventClass])) {
            $this->handlers[$eventClass] = [];
        }

        $this->handlers[$eventClass][] = [
            'handler'  => $handler,
            'priority' => $priority,
        ];

        // Also register with Laravel's event system if it's a class handler
        if (is_string($handler)) {
            $this->dispatcher->listen($eventClass, $handler);
        }
    }

    /**
     * Unsubscribe from a domain event.
     */
    public function unsubscribe(string $eventClass, callable|string $handler): void
    {
        if (! isset($this->handlers[$eventClass])) {
            return;
        }

        $this->handlers[$eventClass] = array_filter(
            $this->handlers[$eventClass],
            fn ($item) => $item['handler'] !== $handler
        );

        // Also unregister from Laravel's event system if needed
        if (is_string($handler)) {
            $this->dispatcher->forget($eventClass);
        }
    }

    /**
     * Publish an event asynchronously.
     */
    public function publishAsync(DomainEvent $event, int $delay = 0): void
    {
        $job = new AsyncDomainEventJob($event);

        if ($delay > 0) {
            Queue::later($delay, $job);
        } else {
            Queue::push($job);
        }
    }

    /**
     * Check if there are handlers for a specific event.
     */
    public function hasHandlers(string $eventClass): bool
    {
        return isset($this->handlers[$eventClass]) && count($this->handlers[$eventClass]) > 0;
    }

    /**
     * Get all registered handlers for an event.
     */
    public function getHandlers(string $eventClass): array
    {
        return $this->handlers[$eventClass] ?? [];
    }

    /**
     * Clear all handlers (useful for testing).
     */
    public function clearHandlers(): void
    {
        $this->handlers = [];
    }

    /**
     * Record events for later publishing (useful in transactions).
     */
    public function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * Dispatch all recorded events.
     */
    public function dispatchRecorded(): void
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        foreach ($events as $event) {
            $this->publish($event);
        }
    }

    /**
     * Clear recorded events without dispatching.
     */
    public function clearRecorded(): void
    {
        $this->recordedEvents = [];
    }

    /**
     * Get all subscribers for a specific event.
     */
    public function getSubscribers(string $eventClass): array
    {
        if (! isset($this->handlers[$eventClass])) {
            return [];
        }

        return array_map(
            fn ($item) => $item['handler'],
            $this->sortHandlersByPriority($this->handlers[$eventClass])
        );
    }

    /**
     * Sort handlers by priority (higher priority first).
     */
    private function sortHandlersByPriority(array $handlers): array
    {
        usort($handlers, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        return $handlers;
    }

    /**
     * Invoke a handler with the event.
     */
    private function invokeHandler(callable|string $handler, DomainEvent $event): void
    {
        if (is_string($handler)) {
            $handler = $this->container->make($handler);
        }

        if (is_object($handler) && method_exists($handler, 'handle')) {
            $handler->handle($event);
        } elseif (is_callable($handler)) {
            $handler($event);
        }
    }
}
