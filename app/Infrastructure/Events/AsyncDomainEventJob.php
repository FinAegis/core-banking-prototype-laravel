<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Domain\Shared\Events\DomainEventBus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job for handling asynchronous domain event processing.
 */
class AsyncDomainEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly DomainEvent $event
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(DomainEventBus $eventBus): void
    {
        $eventBus->publish($this->event);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['domain-event', 'event-bus', get_class($this->event)];
    }
}
