<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS;

use App\Domain\Shared\CQRS\Command;
use App\Domain\Shared\CQRS\CommandBus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queue job for handling asynchronous command execution.
 */
class AsyncCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly Command $command,
        public readonly string $commandClass
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(CommandBus $commandBus): void
    {
        $commandBus->dispatch($this->command);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['command', 'cqrs', $this->commandClass];
    }
}
