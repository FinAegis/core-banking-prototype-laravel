<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\VectorDB;

interface VectorDatabaseInterface
{
    /**
     * Store a vector with metadata.
     *
     * @param array<float> $vector
     */
    public function store(string $id, array $vector, array $metadata = []): void;

    /**
     * Store multiple vectors in batch.
     *
     * @param array<array{id: string, vector: array<float>, metadata: array}> $items
     */
    public function storeBatch(array $items): void;

    /**
     * Search for similar vectors.
     *
     * @param array<float> $queryVector
     * @return array<array{id: string, score: float, metadata: array}>
     */
    public function search(array $queryVector, int $topK = 10, array $filters = []): array;

    /**
     * Delete a vector by ID.
     */
    public function delete(string $id): void;

    /**
     * Delete vectors by filter.
     */
    public function deleteByFilter(array $filters): int;

    /**
     * Get vector by ID.
     *
     * @return array{id: string, vector: array<float>, metadata: array}|null
     */
    public function get(string $id): ?array;

    /**
     * Create or update an index/collection.
     */
    public function createIndex(string $name, int $dimensions, string $metric = 'cosine'): void;

    /**
     * Check if the database is available.
     */
    public function isAvailable(): bool;

    /**
     * Get statistics about the database.
     */
    public function getStats(): array;
}
