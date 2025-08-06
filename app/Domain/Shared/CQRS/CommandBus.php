<?php

declare(strict_types=1);

namespace App\Domain\Shared\CQRS;

/**
 * Command Bus interface for handling commands in CQRS pattern.
 */
interface CommandBus
{
    /**
     * Dispatch a command to its handler.
     *
     * @param Command $command The command to dispatch
     * @return mixed The result of the command execution
     */
    public function dispatch(Command $command): mixed;

    /**
     * Register a command handler.
     *
     * @param string $commandClass The fully qualified class name of the command
     * @param string|callable $handler The handler class or callable
     */
    public function register(string $commandClass, string|callable $handler): void;

    /**
     * Dispatch a command asynchronously.
     *
     * @param Command $command The command to dispatch
     * @param int $delay Delay in seconds before processing
     */
    public function dispatchAsync(Command $command, int $delay = 0): void;

    /**
     * Dispatch multiple commands in a transaction.
     *
     * @param array<Command> $commands The commands to dispatch
     * @return array Results of each command
     */
    public function dispatchTransaction(array $commands): array;
}
