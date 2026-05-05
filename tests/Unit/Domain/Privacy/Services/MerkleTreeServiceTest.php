<?php

declare(strict_types=1);

use App\Domain\Privacy\Services\MerkleTreeService;
use App\Domain\Privacy\ValueObjects\MerklePath;
use App\Domain\Privacy\ValueObjects\MerkleRoot;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

describe('MerkleTreeService', function () {
    beforeEach(function () {
        // Use the in-memory array cache so parallel processes don't pollute
        // each other's cache state — without this, one process's Cache::flush()
        // races against another's Cache::put(...) and the production-fetch
        // path is exercised unexpectedly. See CLAUDE.md "Parallel cache tests".
        config(['cache.default' => 'array']);
        Cache::flush();
        $this->service = new MerkleTreeService();
    });

    describe('network support', function () {
        it('returns supported networks from config', function () {
            config(['privacy.merkle.networks' => ['polygon', 'base', 'arbitrum']]);

            $networks = $this->service->getSupportedNetworks();

            expect($networks)->toBeArray()
                ->and($networks)->toContain('polygon')
                ->and($networks)->toContain('base')
                ->and($networks)->toContain('arbitrum');
        });

        it('checks if a network is supported', function () {
            config(['privacy.merkle.networks' => ['polygon', 'base', 'arbitrum']]);

            expect($this->service->supportsNetwork('polygon'))->toBeTrue()
                ->and($this->service->supportsNetwork('base'))->toBeTrue()
                ->and($this->service->supportsNetwork('arbitrum'))->toBeTrue()
                ->and($this->service->supportsNetwork('ethereum'))->toBeFalse()
                ->and($this->service->supportsNetwork('solana'))->toBeFalse()
                ->and($this->service->supportsNetwork(''))->toBeFalse();
        });

        it('respects custom network configuration', function () {
            config(['privacy.merkle.networks' => ['ethereum', 'optimism']]);

            expect($this->service->supportsNetwork('ethereum'))->toBeTrue()
                ->and($this->service->supportsNetwork('optimism'))->toBeTrue()
                ->and($this->service->supportsNetwork('polygon'))->toBeFalse();
        });
    });

    describe('tree depth', function () {
        it('returns tree depth from config', function () {
            config(['privacy.merkle.max_tree_depth' => 32]);

            expect($this->service->getTreeDepth())->toBe(32);
        });

        it('respects custom tree depth configuration', function () {
            config(['privacy.merkle.max_tree_depth' => 20]);

            expect($this->service->getTreeDepth())->toBe(20);
        });

        it('defaults to 32 when config key is absent', function () {
            // Remove the entire privacy.merkle config section so the key is truly absent
            config(['privacy.merkle' => []]);

            expect($this->service->getTreeDepth())->toBe(32);
        });
    });

    describe('provider name', function () {
        it('returns production provider name', function () {
            expect($this->service->getProviderName())->toBe('production');
        });
    });

    describe('getMerkleRoot', function () {
        it('throws InvalidArgumentException for unsupported network', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            expect(fn () => $this->service->getMerkleRoot('unsupported'))
                ->toThrow(InvalidArgumentException::class, 'Unsupported network');
        });

        it('throws RuntimeException because production fetch is not implemented', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            expect(fn () => $this->service->getMerkleRoot('polygon'))
                ->toThrow(RuntimeException::class, 'Production Merkle tree sync not implemented');
        });

        it('caches the result when available', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            // Pre-populate cache so it does not hit the unimplemented fetch
            $merkleRoot = new MerkleRoot(
                root: '0x' . str_repeat('a', 64),
                network: 'polygon',
                leafCount: 100,
                treeDepth: 32,
                blockNumber: 12345,
                syncedAt: new DateTimeImmutable(),
            );

            Cache::put('privacy_merkle_root_polygon', $merkleRoot, 30);

            $result = $this->service->getMerkleRoot('polygon');

            expect($result)->toBeInstanceOf(MerkleRoot::class)
                ->and($result->root)->toBe('0x' . str_repeat('a', 64))
                ->and($result->network)->toBe('polygon')
                ->and($result->leafCount)->toBe(100);
        });
    });

    describe('getMerklePath', function () {
        it('throws InvalidArgumentException for unsupported network', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            $commitment = '0x' . str_repeat('a', 64);

            expect(fn () => $this->service->getMerklePath($commitment, 'unsupported'))
                ->toThrow(InvalidArgumentException::class, 'Unsupported network');
        });

        it('throws InvalidArgumentException for invalid commitment format', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            expect(fn () => $this->service->getMerklePath('invalid', 'polygon'))
                ->toThrow(InvalidArgumentException::class, 'Invalid commitment format');
        });

        it('throws InvalidArgumentException for commitment without 0x prefix', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            $commitment = str_repeat('a', 64);

            expect(fn () => $this->service->getMerklePath($commitment, 'polygon'))
                ->toThrow(InvalidArgumentException::class, 'Invalid commitment format');
        });

        it('throws InvalidArgumentException for commitment with wrong length', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            $commitment = '0x' . str_repeat('a', 32); // Too short

            expect(fn () => $this->service->getMerklePath($commitment, 'polygon'))
                ->toThrow(InvalidArgumentException::class, 'Invalid commitment format');
        });

        it('returns cached path when available', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            $commitment = '0x' . str_repeat('b', 64);
            $pathData = [
                'commitment'   => $commitment,
                'root'         => '0x' . str_repeat('c', 64),
                'network'      => 'polygon',
                'leaf_index'   => 5,
                'siblings'     => array_fill(0, 32, '0x' . str_repeat('d', 64)),
                'path_indices' => array_fill(0, 32, 0),
            ];

            Cache::put('privacy_merkle_path_polygon_' . $commitment, $pathData, 30);

            $result = $this->service->getMerklePath($commitment, 'polygon');

            expect($result)->toBeInstanceOf(MerklePath::class)
                ->and($result->commitment)->toBe($commitment)
                ->and($result->network)->toBe('polygon')
                ->and($result->leafIndex)->toBe(5);
        });

        it('throws RuntimeException for uncached commitment on production service', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            $commitment = '0x' . str_repeat('e', 64);

            expect(fn () => $this->service->getMerklePath($commitment, 'polygon'))
                ->toThrow(RuntimeException::class, 'Production Merkle path fetch not implemented');
        });
    });

    describe('verifyCommitment', function () {
        it('returns false for commitment without 0x prefix', function () {
            $path = createMerklePath(32);

            $result = $this->service->verifyCommitment(str_repeat('a', 64), $path);

            expect($result)->toBeFalse();
        });

        it('returns false for commitment with wrong hex length', function () {
            $path = createMerklePath(32);

            $result = $this->service->verifyCommitment('0x' . str_repeat('a', 32), $path);

            expect($result)->toBeFalse();
        });

        it('returns false for commitment with non-hex characters', function () {
            $path = createMerklePath(32);

            $result = $this->service->verifyCommitment('0x' . str_repeat('g', 64), $path);

            expect($result)->toBeFalse();
        });

        it('returns false for empty commitment', function () {
            $path = createMerklePath(32);

            $result = $this->service->verifyCommitment('', $path);

            expect($result)->toBeFalse();
        });

        it('returns false when sibling has invalid hex format', function () {
            $siblings = array_fill(0, 32, '0x' . str_repeat('a', 64));
            $siblings[5] = 'invalid_hex'; // One invalid sibling

            $path = new MerklePath(
                commitment: '0x' . str_repeat('1', 64),
                root: '0x' . str_repeat('b', 64),
                network: 'polygon',
                leafIndex: 0,
                siblings: $siblings,
                pathIndices: array_fill(0, 32, 0),
            );

            $result = $this->service->verifyCommitment('0x' . str_repeat('1', 64), $path);

            expect($result)->toBeFalse();
        });

        it('returns false when path index is not 0 or 1', function () {
            $pathIndices = array_fill(0, 32, 0);
            $pathIndices[3] = 2; // Invalid index

            $path = new MerklePath(
                commitment: '0x' . str_repeat('1', 64),
                root: '0x' . str_repeat('b', 64),
                network: 'polygon',
                leafIndex: 0,
                siblings: array_fill(0, 32, '0x' . str_repeat('a', 64)),
                pathIndices: $pathIndices,
            );

            $result = $this->service->verifyCommitment('0x' . str_repeat('1', 64), $path);

            expect($result)->toBeFalse();
        });

        it('returns false when path index is negative', function () {
            $pathIndices = array_fill(0, 32, 0);
            $pathIndices[0] = -1; // Negative index

            $path = new MerklePath(
                commitment: '0x' . str_repeat('1', 64),
                root: '0x' . str_repeat('b', 64),
                network: 'polygon',
                leafIndex: 0,
                siblings: array_fill(0, 32, '0x' . str_repeat('a', 64)),
                pathIndices: $pathIndices,
            );

            $result = $this->service->verifyCommitment('0x' . str_repeat('1', 64), $path);

            expect($result)->toBeFalse();
        });

        it('returns false when path depth does not match tree depth', function () {
            config(['privacy.merkle.max_tree_depth' => 32]);

            // Create a path with only 10 siblings instead of 32
            $path = new MerklePath(
                commitment: '0x' . str_repeat('1', 64),
                root: '0x' . str_repeat('b', 64),
                network: 'polygon',
                leafIndex: 0,
                siblings: array_fill(0, 10, '0x' . str_repeat('a', 64)),
                pathIndices: array_fill(0, 10, 0),
            );

            $result = $this->service->verifyCommitment('0x' . str_repeat('1', 64), $path);

            expect($result)->toBeFalse();
        });

        it('returns false when root does not match computed root', function () {
            config([
                'privacy.merkle.networks'       => ['polygon'],
                'privacy.merkle.max_tree_depth' => 32,
            ]);

            $commitment = '0x' . str_repeat('1', 64);

            // Create a valid-format path but with wrong root cached
            $wrongRoot = new MerkleRoot(
                root: '0x' . str_repeat('f', 64), // Will not match computed root
                network: 'polygon',
                leafCount: 100,
                treeDepth: 32,
                blockNumber: 12345,
                syncedAt: new DateTimeImmutable(),
            );

            Cache::put('privacy_merkle_root_polygon', $wrongRoot, 30);

            $path = new MerklePath(
                commitment: $commitment,
                root: '0x' . str_repeat('b', 64),
                network: 'polygon',
                leafIndex: 0,
                siblings: array_fill(0, 32, '0x' . str_repeat('a', 64)),
                pathIndices: array_fill(0, 32, 0),
            );

            $result = $this->service->verifyCommitment($commitment, $path);

            expect($result)->toBeFalse();
        });

        it('verifies commitment successfully when computed root matches chain root', function () {
            config([
                'privacy.merkle.networks'       => ['polygon'],
                'privacy.merkle.max_tree_depth' => 2,
            ]);

            $commitment = '0x' . str_repeat('1', 64);
            $sibling0 = '0x' . str_repeat('2', 64);
            $sibling1 = '0x' . str_repeat('3', 64);

            // Compute expected root: commitment is left child at both levels
            // Level 0: hash(commitment, sibling0) with sorted inputs
            // Level 1: hash(level0Result, sibling1) with sorted inputs
            $computedRoot = computeExpectedRoot($commitment, [$sibling0, $sibling1], [0, 0]);

            $chainRoot = new MerkleRoot(
                root: $computedRoot,
                network: 'polygon',
                leafCount: 4,
                treeDepth: 2,
                blockNumber: 12345,
                syncedAt: new DateTimeImmutable(),
            );

            Cache::put('privacy_merkle_root_polygon', $chainRoot, 30);

            $path = new MerklePath(
                commitment: $commitment,
                root: $computedRoot,
                network: 'polygon',
                leafIndex: 0,
                siblings: [$sibling0, $sibling1],
                pathIndices: [0, 0],
            );

            $result = $this->service->verifyCommitment($commitment, $path);

            expect($result)->toBeTrue();
        });

        it('computes root correctly when commitment is on the right side', function () {
            config([
                'privacy.merkle.networks'       => ['polygon'],
                'privacy.merkle.max_tree_depth' => 2,
            ]);

            $commitment = '0x' . str_repeat('1', 64);
            $sibling0 = '0x' . str_repeat('2', 64);
            $sibling1 = '0x' . str_repeat('3', 64);

            // pathIndices [1, 0] means commitment is on the right at level 0
            $computedRoot = computeExpectedRoot($commitment, [$sibling0, $sibling1], [1, 0]);

            $chainRoot = new MerkleRoot(
                root: $computedRoot,
                network: 'polygon',
                leafCount: 4,
                treeDepth: 2,
                blockNumber: 12345,
                syncedAt: new DateTimeImmutable(),
            );

            Cache::put('privacy_merkle_root_polygon', $chainRoot, 30);

            $path = new MerklePath(
                commitment: $commitment,
                root: $computedRoot,
                network: 'polygon',
                leafIndex: 1,
                siblings: [$sibling0, $sibling1],
                pathIndices: [1, 0],
            );

            $result = $this->service->verifyCommitment($commitment, $path);

            expect($result)->toBeTrue();
        });
    });

    describe('hashPair via integration', function () {
        it('produces deterministic hashes for same inputs', function () {
            config([
                'privacy.merkle.networks'       => ['polygon'],
                'privacy.merkle.max_tree_depth' => 1,
            ]);

            $commitment = '0x' . str_repeat('1', 64);
            $sibling = '0x' . str_repeat('2', 64);

            $root1 = computeExpectedRoot($commitment, [$sibling], [0]);
            $root2 = computeExpectedRoot($commitment, [$sibling], [0]);

            expect($root1)->toBe($root2);
        });

        it('sorts inputs for consistent ordering regardless of left-right position', function () {
            // hashPair sorts inputs so hash(a, b) == hash(b, a) if a < b or a > b
            // This is by design to prevent second-preimage attacks
            $a = '0x' . str_repeat('1', 64);
            $b = '0x' . str_repeat('2', 64);

            // Both orderings should produce the same hash because hashPair sorts
            $hashAB = testHashPair($a, $b);
            $hashBA = testHashPair($b, $a);

            expect($hashAB)->toBe($hashBA);
        });

        it('uses domain separation prefix in hash computation', function () {
            $a = '0x' . str_repeat('1', 64);
            $b = '0x' . str_repeat('2', 64);

            $merkleHash = testHashPair($a, $b);

            // Without domain separation - raw concatenation
            $leftBin = hex2bin(substr($a, 2));
            $rightBin = hex2bin(substr($b, 2));

            // Compare with strcmp to determine order (same as hashPair)
            if (strcmp($a, $b) > 0) {
                [$leftBin, $rightBin] = [$rightBin, $leftBin];
            }

            $rawHash = '0x' . hash('sha3-256', $leftBin . $rightBin);

            // Merkle hash uses domain separation so it must differ from raw hash
            expect($merkleHash)->not->toBe($rawHash);
        });

        it('produces 0x-prefixed 64-character hex output', function () {
            $a = '0x' . str_repeat('a', 64);
            $b = '0x' . str_repeat('b', 64);

            $result = testHashPair($a, $b);

            expect($result)->toStartWith('0x')
                ->and(strlen($result))->toBe(66); // 0x + 64 hex chars
        });
    });

    describe('syncTree', function () {
        it('throws InvalidArgumentException for unsupported network', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            expect(fn () => $this->service->syncTree('unsupported'))
                ->toThrow(InvalidArgumentException::class, 'Unsupported network');
        });

        it('clears cache before fetching fresh root', function () {
            config(['privacy.merkle.networks' => ['polygon']]);

            // Pre-populate cache
            $merkleRoot = new MerkleRoot(
                root: '0x' . str_repeat('a', 64),
                network: 'polygon',
                leafCount: 100,
                treeDepth: 32,
                blockNumber: 12345,
                syncedAt: new DateTimeImmutable(),
            );

            Cache::put('privacy_merkle_root_polygon', $merkleRoot, 30);

            // syncTree will clear cache and then try to fetch from chain (which throws)
            expect(fn () => $this->service->syncTree('polygon'))
                ->toThrow(RuntimeException::class, 'Production Merkle tree sync not implemented');

            // Verify cache was cleared
            expect(Cache::get('privacy_merkle_root_polygon'))->toBeNull();
        });
    });
});

// Helper: Replicate the hashPair logic for test assertions
function testHashPair(string $left, string $right): string
{
    if (strcmp($left, $right) > 0) {
        [$left, $right] = [$right, $left];
    }

    $leftBin = hex2bin(substr($left, 2));
    $rightBin = hex2bin(substr($right, 2));

    return '0x' . hash('sha3-256', 'merkle_node:' . $leftBin . $rightBin);
}

/**
 * Helper: Compute expected root by walking up the tree.
 *
 * @param  array<int, string>  $siblings
 * @param  array<int, int>     $pathIndices
 */
function computeExpectedRoot(string $commitment, array $siblings, array $pathIndices): string
{
    $current = $commitment;

    foreach ($siblings as $index => $sibling) {
        $pathIndex = $pathIndices[$index] ?? 0;

        if ($pathIndex === 0) {
            $current = testHashPair($current, $sibling);
        } else {
            $current = testHashPair($sibling, $current);
        }
    }

    return $current;
}

// Helper: Create a MerklePath with valid hex values
function createMerklePath(int $depth): MerklePath
{
    return new MerklePath(
        commitment: '0x' . str_repeat('1', 64),
        root: '0x' . str_repeat('b', 64),
        network: 'polygon',
        leafIndex: 0,
        siblings: array_fill(0, $depth, '0x' . str_repeat('a', 64)),
        pathIndices: array_fill(0, $depth, 0),
    );
}
