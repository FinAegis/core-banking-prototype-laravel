<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Contracts;

use App\Domain\KeyManagement\ValueObjects\KeyShard;
use App\Domain\KeyManagement\ValueObjects\ReconstructedKey;

interface ShamirServiceInterface
{
    /**
     * Split a private key into shards using Shamir's Secret Sharing.
     *
     * @return array<string, KeyShard>
     */
    public function splitKey(string $privateKey, string $userId): array;

    /**
     * Reconstruct a key from shards (requires threshold number of shards).
     */
    public function reconstructKey(KeyShard $shard1, KeyShard $shard2): ReconstructedKey;

    /**
     * Verify that a set of shards can reconstruct the original key.
     *
     * @param array<KeyShard> $shards
     */
    public function verifyShards(array $shards, string $expectedPublicKey): bool;

    /**
     * Get the configured threshold (minimum shards required).
     */
    public function getThreshold(): int;

    /**
     * Get the total number of shards created.
     */
    public function getTotalShards(): int;
}
