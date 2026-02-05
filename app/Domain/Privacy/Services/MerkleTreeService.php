<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\MerkleTreeServiceInterface;
use App\Domain\Privacy\Events\Broadcast\MerkleRootUpdated;
use App\Domain\Privacy\ValueObjects\MerklePath;
use App\Domain\Privacy\ValueObjects\MerkleRoot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

/**
 * Production Merkle tree service that syncs with on-chain privacy pools.
 *
 * In production, this would integrate with:
 * - RAILGUN-style privacy pools
 * - Aztec Protocol
 * - Tornado Cash Nova (where legal)
 * - Custom privacy pool contracts
 */
class MerkleTreeService implements MerkleTreeServiceInterface
{
    private const CACHE_PREFIX = 'privacy_merkle_';

    private const CACHE_TTL_SECONDS = 30;

    public function __construct()
    {
        // In production, inject RPC clients for each network
    }

    public function getMerkleRoot(string $network): MerkleRoot
    {
        $this->validateNetwork($network);

        $cacheKey = self::CACHE_PREFIX . 'root_' . $network;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($network) {
            return $this->fetchMerkleRootFromChain($network);
        });
    }

    public function getMerklePath(string $commitment, string $network): MerklePath
    {
        $this->validateNetwork($network);
        $this->validateCommitment($commitment);

        // Check cache first
        $cacheKey = self::CACHE_PREFIX . 'path_' . $network . '_' . $commitment;
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return MerklePath::fromArray($cached);
        }

        // Fetch from chain
        $path = $this->fetchMerklePathFromChain($commitment, $network);

        // Cache the result
        Cache::put($cacheKey, $path->toArray(), self::CACHE_TTL_SECONDS);

        return $path;
    }

    public function verifyCommitment(string $commitment, MerklePath $path): bool
    {
        // Validate commitment format (32-byte hex with 0x prefix)
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $commitment)) {
            return false;
        }

        // Validate all siblings in the path
        foreach ($path->siblings as $sibling) {
            if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $sibling)) {
                return false;
            }
        }

        // Validate path indices only contain 0 or 1
        foreach ($path->pathIndices as $index) {
            if ($index !== 0 && $index !== 1) {
                return false;
            }
        }

        // Verify the path length matches tree depth
        if (! $path->isValidForDepth($this->getTreeDepth())) {
            return false;
        }

        // Compute root from commitment and path
        $computedRoot = $this->computeRoot($commitment, $path);

        // Get current root from chain
        $currentRoot = $this->getMerkleRoot($path->network);

        return hash_equals($currentRoot->root, $computedRoot);
    }

    public function supportsNetwork(string $network): bool
    {
        return in_array($network, $this->getSupportedNetworks(), true);
    }

    public function getSupportedNetworks(): array
    {
        return config('privacy.merkle.networks', ['polygon', 'base', 'arbitrum']);
    }

    public function syncTree(string $network): MerkleRoot
    {
        $this->validateNetwork($network);

        // Clear cache to force fresh fetch
        $cacheKey = self::CACHE_PREFIX . 'root_' . $network;
        Cache::forget($cacheKey);

        Log::info('Merkle tree sync triggered', ['network' => $network]);

        $root = $this->getMerkleRoot($network);

        // Broadcast the updated root to connected clients
        MerkleRootUpdated::dispatch(
            $network,
            $root->root,
            $root->leafCount,
            $root->blockNumber,
            $root->treeDepth,
            $root->syncedAt->format('c'),
        );

        return $root;
    }

    public function getTreeDepth(): int
    {
        return (int) config('privacy.merkle.max_tree_depth', 32);
    }

    public function getProviderName(): string
    {
        return 'production';
    }

    private function validateNetwork(string $network): void
    {
        if (! $this->supportsNetwork($network)) {
            throw new InvalidArgumentException(
                "Unsupported network: {$network}. Supported: " . implode(', ', $this->getSupportedNetworks())
            );
        }
    }

    private function validateCommitment(string $commitment): void
    {
        // Commitments should be 32-byte hex strings (64 characters + 0x prefix)
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $commitment)) {
            throw new InvalidArgumentException('Invalid commitment format. Expected 32-byte hex string with 0x prefix.');
        }
    }

    private function fetchMerkleRootFromChain(string $network): MerkleRoot
    {
        // In production, this would call the privacy pool contract
        // For now, throw to indicate production implementation needed
        throw new RuntimeException(
            'Production Merkle tree sync not implemented. Use DemoMerkleTreeService for development.'
        );
    }

    private function fetchMerklePathFromChain(string $commitment, string $network): MerklePath
    {
        // In production, this would:
        // 1. Find the commitment's leaf index
        // 2. Compute the Merkle proof path
        throw new RuntimeException(
            'Production Merkle path fetch not implemented. Use DemoMerkleTreeService for development.'
        );
    }

    private function computeRoot(string $commitment, MerklePath $path): string
    {
        $current = $commitment;

        foreach ($path->siblings as $index => $sibling) {
            $pathIndex = $path->pathIndices[$index] ?? 0;

            if ($pathIndex === 0) {
                // Current is on the left
                $current = $this->hashPair($current, $sibling);
            } else {
                // Current is on the right
                $current = $this->hashPair($sibling, $current);
            }
        }

        return $current;
    }

    private function hashPair(string $left, string $right): string
    {
        // Sort inputs for consistent ordering (prevents second-preimage attacks)
        if (strcmp($left, $right) > 0) {
            [$left, $right] = [$right, $left];
        }

        // Domain separation prefix prevents cross-level collision attacks
        $leftBin = hex2bin(substr($left, 2));
        $rightBin = hex2bin(substr($right, 2));

        if ($leftBin === false || $rightBin === false) {
            throw new RuntimeException('Invalid hex input for Merkle hash');
        }

        // Poseidon hash would be used in production
        // Using sha3-256 as placeholder with domain separation
        return '0x' . hash('sha3-256', 'merkle_node:' . $leftBin . $rightBin);
    }
}
