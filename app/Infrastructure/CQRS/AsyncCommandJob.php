<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS;

use App\Domain\Shared\CQRS\Command;
use App\Domain\Shared\CQRS\CommandBus;
use App\Domain\Shared\Jobs\TenantAwareJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job for handling asynchronous command execution.
 *
 * This job is tenant-aware and will automatically initialize the correct
 * tenant context when processed from the queue. The tenant_id is captured
 * at dispatch time and restored by stancl/tenancy's QueueTenancyBootstrapper.
 */
class AsyncCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use TenantAwareJob;

    public function __construct(
        public readonly Command $command,
        public readonly string $commandClass
    ) {
        $this->initializeTenantAwareJob();
    }

    /**
     * Execute the job.
     */
    public function handle(CommandBus $commandBus): void
    {
        $commandBus->dispatch($this->command);
    }

    /**
     * Command jobs may run in central context for global commands.
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
            ['command', 'cqrs', $this->commandClass],
            $this->tenantTags()
        );
    }
}
