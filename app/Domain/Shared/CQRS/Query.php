<?php

declare(strict_types=1);

namespace App\Domain\Shared\CQRS;

/**
 * Base interface for all queries in the CQRS pattern.
 */
interface Query
{
    /**
     * Get a unique identifier for this query instance.
     */
    public function getQueryId(): string;

    /**
     * Get a cache key for this query.
     * Queries with the same cache key should return the same result.
     */
    public function getCacheKey(): string;

    /**
     * Whether this query result can be cached.
     */
    public function isCacheable(): bool;

    /**
     * Get the cache TTL in seconds.
     */
    public function getCacheTtl(): int;

    /**
     * Convert the query to an array for serialization.
     */
    public function toArray(): array;
}
