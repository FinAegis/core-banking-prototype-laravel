<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS;

use App\Domain\Shared\CQRS\Command;
use App\Domain\Shared\CQRS\CommandBus;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use RuntimeException;

/**
 * Laravel implementation of the Command Bus pattern.
 * Handles command dispatching with support for synchronous, asynchronous, and transactional execution.
 */
class LaravelCommandBus implements CommandBus
{
    /**
     * Registered command handlers.
     *
     * @var array<string, string|callable>
     */
    private array $handlers = [];

    public function __construct(
        private readonly Container $container
    ) {
    }

    /**
     * Dispatch a command to its handler.
     */
    public function dispatch(Command $command): mixed
    {
        $commandClass = get_class($command);

        if (! isset($this->handlers[$commandClass])) {
            throw new InvalidArgumentException("No handler registered for command: {$commandClass}");
        }

        $handler = $this->resolveHandler($this->handlers[$commandClass]);

        // Call the handle method on the handler
        if (is_object($handler) && method_exists($handler, 'handle')) {
            return $handler->handle($command);
        }

        // If it's a callable, invoke it directly
        if (is_callable($handler)) {
            return $handler($command);
        }

        throw new RuntimeException("Handler for {$commandClass} is not callable");
    }

    /**
     * Register a command handler.
     */
    public function register(string $commandClass, string|callable $handler): void
    {
        $this->handlers[$commandClass] = $handler;
    }

    /**
     * Dispatch a command asynchronously using Laravel's queue system.
     */
    public function dispatchAsync(Command $command, int $delay = 0): void
    {
        $job = new AsyncCommandJob($command, get_class($command));

        if ($delay > 0) {
            Queue::later($delay, $job);
        } else {
            Queue::push($job);
        }
    }

    /**
     * Dispatch multiple commands in a database transaction.
     */
    public function dispatchTransaction(array $commands): array
    {
        return DB::transaction(function () use ($commands) {
            $results = [];

            foreach ($commands as $command) {
                if (! $command instanceof Command) {
                    throw new InvalidArgumentException('All items must be Command instances');
                }

                $results[] = $this->dispatch($command);
            }

            return $results;
        });
    }

    /**
     * Resolve a handler from the container.
     */
    private function resolveHandler(string|callable $handler): mixed
    {
        if (is_string($handler)) {
            return $this->container->make($handler);
        }

        return $handler;
    }
}
