<?php

declare(strict_types=1);

namespace App\Infrastructure\Events;

use App\Domain\Shared\Events\DomainEvent;
use App\Domain\Shared\Events\DomainEventBus;
use App\Domain\Shared\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job for handling asynchronous domain event processing.
 *
 * This job is tenant-aware and will automatically initialize the correct
 * tenant context when processed from the queue. The tenant_id is captured
 * at dispatch time and restored by stancl/tenancy's QueueTenancyBootstrapper.
 */
class AsyncDomainEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TenantAwareJob;

    public function __construct(
        public readonly DomainEvent $event
    ) {
        $this->initializeTenantAwareJob();
    }

    /**
     * Execute the job.
     */
    public function handle(DomainEventBus $eventBus): void
    {
        $eventBus->publish($this->event);
    }

    /**
     * Domain events may be published in central context for global events.
     */
    public function requiresTenantContext(): bool
    {
        return false;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return array_merge(
            ['domain-event', 'event-bus', get_class($this->event)],
            $this->tenantTags()
        );
    }
}
