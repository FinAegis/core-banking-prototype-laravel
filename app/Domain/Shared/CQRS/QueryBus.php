<?php

declare(strict_types=1);

namespace App\Domain\Shared\CQRS;

/**
 * Query Bus interface for handling queries in CQRS pattern.
 */
interface QueryBus
{
    /**
     * Ask a query and get the result.
     *
     * @param Query $query The query to ask
     * @return mixed The query result
     */
    public function ask(Query $query): mixed;

    /**
     * Register a query handler.
     *
     * @param string $queryClass The fully qualified class name of the query
     * @param string|callable $handler The handler class or callable
     */
    public function register(string $queryClass, string|callable $handler): void;

    /**
     * Ask a query with caching.
     *
     * @param Query $query The query to ask
     * @param int $ttl Cache time-to-live in seconds
     * @return mixed The query result
     */
    public function askCached(Query $query, int $ttl = 3600): mixed;

    /**
     * Ask multiple queries in parallel.
     *
     * @param array<Query> $queries The queries to ask
     * @return array Results of each query
     */
    public function askMultiple(array $queries): array;
}
