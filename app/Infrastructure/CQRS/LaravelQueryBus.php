<?php

declare(strict_types=1);

namespace App\Infrastructure\CQRS;

use App\Domain\Shared\CQRS\Query;
use App\Domain\Shared\CQRS\QueryBus;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use RuntimeException;

/**
 * Laravel implementation of the Query Bus pattern.
 * Handles query dispatching with support for caching and parallel execution.
 */
class LaravelQueryBus implements QueryBus
{
    /**
     * Registered query handlers.
     *
     * @var array<string, string|callable>
     */
    private array $handlers = [];

    public function __construct(
        private readonly Container $container,
        private readonly CacheRepository $cache
    ) {
    }

    /**
     * Ask a query and get the result.
     */
    public function ask(Query $query): mixed
    {
        $queryClass = get_class($query);

        if (! isset($this->handlers[$queryClass])) {
            throw new InvalidArgumentException("No handler registered for query: {$queryClass}");
        }

        $handler = $this->resolveHandler($this->handlers[$queryClass]);

        // Call the handle method on the handler
        if (is_object($handler) && method_exists($handler, 'handle')) {
            return $handler->handle($query);
        }

        // If it's a callable, invoke it directly
        if (is_callable($handler)) {
            return $handler($query);
        }

        throw new RuntimeException("Handler for {$queryClass} is not callable");
    }

    /**
     * Register a query handler.
     */
    public function register(string $queryClass, string|callable $handler): void
    {
        $this->handlers[$queryClass] = $handler;
    }

    /**
     * Ask a query with caching.
     */
    public function askCached(Query $query, int $ttl = 3600): mixed
    {
        $cacheKey = $this->getCacheKey($query);

        return $this->cache->remember($cacheKey, $ttl, function () use ($query) {
            return $this->ask($query);
        });
    }

    /**
     * Ask multiple queries in parallel.
     * Note: This is a simplified implementation. For true parallel execution,
     * consider using Laravel's concurrent facade or queue workers.
     */
    public function askMultiple(array $queries): array
    {
        $results = [];

        foreach ($queries as $key => $query) {
            if (! $query instanceof Query) {
                throw new InvalidArgumentException('All items must be Query instances');
            }

            $results[$key] = $this->ask($query);
        }

        return $results;
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

    /**
     * Generate a cache key for a query.
     */
    private function getCacheKey(Query $query): string
    {
        $class = get_class($query);
        $data = serialize($query);

        return 'query:' . md5($class . ':' . $data);
    }
}
