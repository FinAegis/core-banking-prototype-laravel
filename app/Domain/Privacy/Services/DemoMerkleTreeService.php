<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\MerkleTreeServiceInterface;
use App\Domain\Privacy\Events\Broadcast\MerkleRootUpdated;
use App\Domain\Privacy\Exceptions\CommitmentNotFoundException;
use App\Domain\Privacy\ValueObjects\MerklePath;
use App\Domain\Privacy\ValueObjects\MerkleRoot;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Demo implementation of Merkle tree service for development and testing.
 *
 * Returns deterministic responses without requiring blockchain connectivity.
 */
class DemoMerkleTreeService implements MerkleTreeServiceInterface
{
    private const DEMO_TREE_DEPTH = 32;

    private const CACHE_PREFIX = 'demo_merkle_';

    /**
     * Demo commitments that are "known" to exist in the tree.
     *
     * @var array<string, array<string, int>>
     */
    private array $knownCommitments = [];

    public function __construct()
    {
        // Seed some demo commitments for each network
        $this->seedDemoCommitments();
    }

    public function getMerkleRoot(string $network): MerkleRoot
    {
        $this->validateNetwork($network);

        $cacheKey = self::CACHE_PREFIX . 'root_' . $network;

        return Cache::remember($cacheKey, 30, function () use ($network) {
            return $this->generateDemoRoot($network);
        });
    }

    public function getMerklePath(string $commitment, string $network): MerklePath
    {
        $this->validateNetwork($network);
        $this->validateCommitment($commitment);

        // Check if commitment is known
        $leafIndex = $this->findCommitmentIndex($commitment, $network);

        if ($leafIndex === null) {
            throw new CommitmentNotFoundException($commitment, $network);
        }

        $root = $this->getMerkleRoot($network);

        return $this->generateDemoPath($commitment, $network, $leafIndex, $root->root);
    }

    public function verifyCommitment(string $commitment, MerklePath $path): bool
    {
        // Validate path structure
        if (! $path->isValidForDepth(self::DEMO_TREE_DEPTH)) {
            return false;
        }

        // In demo mode, verify by checking if commitment is known
        $leafIndex = $this->findCommitmentIndex($commitment, $path->network);

        if ($leafIndex === null) {
            return false;
        }

        // Verify path indices match leaf index
        $computedIndex = $this->computeLeafIndexFromPath($path->pathIndices);

        return $computedIndex === $leafIndex;
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

        // Clear cached root to simulate sync
        $cacheKey = self::CACHE_PREFIX . 'root_' . $network;
        Cache::forget($cacheKey);

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
        return self::DEMO_TREE_DEPTH;
    }

    public function getProviderName(): string
    {
        return 'demo';
    }

    /**
     * Add a commitment to the demo tree (for testing).
     */
    public function addDemoCommitment(string $commitment, string $network): int
    {
        $this->validateNetwork($network);
        $this->validateCommitment($commitment);

        // Normalize the commitment before storing
        $normalizedCommitment = $this->normalizeCommitment($commitment);

        if (! isset($this->knownCommitments[$network])) {
            $this->knownCommitments[$network] = [];
        }

        // Check if already exists (return existing index)
        if (isset($this->knownCommitments[$network][$normalizedCommitment])) {
            return $this->knownCommitments[$network][$normalizedCommitment];
        }

        $leafIndex = count($this->knownCommitments[$network]);
        $this->knownCommitments[$network][$normalizedCommitment] = $leafIndex;

        // Update cached root
        $this->syncTree($network);

        return $leafIndex;
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
        // Accept hex strings with or without 0x prefix, 64 chars
        if (! preg_match('/^(0x)?[a-fA-F0-9]{64}$/', $commitment)) {
            throw new InvalidArgumentException('Invalid commitment format. Expected 32-byte hex string.');
        }
    }

    private function seedDemoCommitments(): void
    {
        // Pre-seed some demo commitments for testing
        $demoCommitments = [
            '0x' . str_repeat('1', 64),
            '0x' . str_repeat('2', 64),
            '0x' . str_repeat('3', 64),
            '0xabc123' . str_repeat('0', 58),
            '0xdef456' . str_repeat('0', 58),
        ];

        foreach ($this->getSupportedNetworks() as $network) {
            $this->knownCommitments[$network] = [];
            foreach ($demoCommitments as $index => $commitment) {
                $this->knownCommitments[$network][$commitment] = $index;
            }
        }
    }

    private function findCommitmentIndex(string $commitment, string $network): ?int
    {
        // Normalize commitment format
        $normalizedCommitment = $this->normalizeCommitment($commitment);

        return $this->knownCommitments[$network][$normalizedCommitment] ?? null;
    }

    private function normalizeCommitment(string $commitment): string
    {
        // Ensure 0x prefix and lowercase
        $commitment = strtolower($commitment);
        if (! str_starts_with($commitment, '0x')) {
            $commitment = '0x' . $commitment;
        }

        return $commitment;
    }

    private function generateDemoRoot(string $network): MerkleRoot
    {
        // Generate deterministic root based on network and known commitments
        $commitmentCount = count($this->knownCommitments[$network] ?? []);
        $rootSeed = $network . '_' . $commitmentCount . '_' . time();

        return new MerkleRoot(
            root: '0x' . hash('sha256', $rootSeed),
            network: $network,
            leafCount: max(1, $commitmentCount),
            treeDepth: self::DEMO_TREE_DEPTH,
            blockNumber: $this->getDemoBlockNumber($network),
            syncedAt: new DateTimeImmutable(),
        );
    }

    private function generateDemoPath(
        string $commitment,
        string $network,
        int $leafIndex,
        string $root
    ): MerklePath {
        $siblings = [];
        $pathIndices = [];

        // Generate deterministic siblings and path indices based on leaf index
        for ($i = 0; $i < self::DEMO_TREE_DEPTH; $i++) {
            // Sibling hash - deterministic based on level and leaf index
            $siblingData = $network . '_sibling_' . $leafIndex . '_level_' . $i;
            $siblings[] = '0x' . hash('sha256', $siblingData);

            // Path index - extract from leaf index bits
            $pathIndices[] = ($leafIndex >> $i) & 1;
        }

        return new MerklePath(
            commitment: $this->normalizeCommitment($commitment),
            root: $root,
            network: $network,
            leafIndex: $leafIndex,
            siblings: $siblings,
            pathIndices: $pathIndices,
        );
    }

    /**
     * @param array<int, int> $pathIndices
     */
    private function computeLeafIndexFromPath(array $pathIndices): int
    {
        $index = 0;
        foreach ($pathIndices as $level => $bit) {
            $index |= ($bit << $level);
        }

        return $index;
    }

    private function getDemoBlockNumber(string $network): int
    {
        // Return a realistic-looking block number for each network
        return match ($network) {
            'polygon'  => 55000000 + (int) (time() / 2),  // ~2s block time
            'base'     => 12000000 + (int) (time() / 2),
            'arbitrum' => 180000000 + (int) (time() / 4), // ~0.25s block time
            default    => 100000000,
        };
    }
}
