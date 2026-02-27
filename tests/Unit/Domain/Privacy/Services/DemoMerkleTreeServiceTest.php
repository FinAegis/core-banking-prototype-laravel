<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\Services;

use App\Domain\Privacy\Exceptions\CommitmentNotFoundException;
use App\Domain\Privacy\Services\DemoMerkleTreeService;
use App\Domain\Privacy\ValueObjects\MerklePath;
use App\Domain\Privacy\ValueObjects\MerkleRoot;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class DemoMerkleTreeServiceTest extends TestCase
{
    private DemoMerkleTreeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->service = new DemoMerkleTreeService();
    }

    public function test_get_supported_networks(): void
    {
        $networks = $this->service->getSupportedNetworks();

        $this->assertIsArray($networks);
        $this->assertContains('polygon', $networks);
        $this->assertContains('ethereum', $networks);
        $this->assertContains('arbitrum', $networks);
        $this->assertContains('bsc', $networks);
    }

    public function test_supports_network(): void
    {
        $this->assertTrue($this->service->supportsNetwork('polygon'));
        $this->assertTrue($this->service->supportsNetwork('ethereum'));
        $this->assertTrue($this->service->supportsNetwork('arbitrum'));
        $this->assertTrue($this->service->supportsNetwork('bsc'));
        $this->assertFalse($this->service->supportsNetwork('base'));
        $this->assertFalse($this->service->supportsNetwork('invalid'));
    }

    public function test_get_tree_depth(): void
    {
        $depth = $this->service->getTreeDepth();

        $this->assertEquals(32, $depth);
    }

    public function test_get_provider_name(): void
    {
        $this->assertEquals('demo', $this->service->getProviderName());
    }

    public function test_get_merkle_root_returns_valid_root(): void
    {
        $root = $this->service->getMerkleRoot('polygon');

        $this->assertInstanceOf(MerkleRoot::class, $root);
        $this->assertEquals('polygon', $root->network);
        $this->assertStringStartsWith('0x', $root->root);
        $this->assertEquals(64 + 2, strlen($root->root)); // 0x + 64 hex chars
        $this->assertEquals(32, $root->treeDepth);
        $this->assertGreaterThan(0, $root->blockNumber);
    }

    public function test_get_merkle_root_throws_for_invalid_network(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported network');

        $this->service->getMerkleRoot('invalid');
    }

    public function test_get_merkle_path_for_known_commitment(): void
    {
        // Use a pre-seeded demo commitment
        $commitment = '0x' . str_repeat('1', 64);

        $path = $this->service->getMerklePath($commitment, 'polygon');

        $this->assertInstanceOf(MerklePath::class, $path);
        $this->assertEquals(strtolower($commitment), $path->commitment);
        $this->assertEquals('polygon', $path->network);
        $this->assertCount(32, $path->siblings);
        $this->assertCount(32, $path->pathIndices);
    }

    public function test_get_merkle_path_throws_for_unknown_commitment(): void
    {
        $this->expectException(CommitmentNotFoundException::class);

        $unknownCommitment = '0x' . str_repeat('9', 64);
        $this->service->getMerklePath($unknownCommitment, 'polygon');
    }

    public function test_get_merkle_path_throws_for_invalid_network(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->getMerklePath('0x' . str_repeat('1', 64), 'invalid');
    }

    public function test_get_merkle_path_throws_for_invalid_commitment_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid commitment format');

        $this->service->getMerklePath('invalid', 'polygon');
    }

    public function test_add_demo_commitment(): void
    {
        $newCommitment = '0x' . str_repeat('f', 64);

        $leafIndex = $this->service->addDemoCommitment($newCommitment, 'polygon');

        // leafIndex is typed as int in the method signature
        $this->assertGreaterThanOrEqual(0, $leafIndex);

        // Should now be able to get the path
        $path = $this->service->getMerklePath($newCommitment, 'polygon');
        $this->assertEquals($leafIndex, $path->leafIndex);
    }

    public function test_verify_commitment_with_valid_path(): void
    {
        $commitment = '0x' . str_repeat('1', 64);
        $path = $this->service->getMerklePath($commitment, 'polygon');

        $isValid = $this->service->verifyCommitment($commitment, $path);

        $this->assertTrue($isValid);
    }

    public function test_verify_commitment_with_wrong_path_depth(): void
    {
        // Create a path with wrong depth
        $path = new MerklePath(
            commitment: '0x' . str_repeat('1', 64),
            root: '0x' . str_repeat('b', 64),
            network: 'polygon',
            leafIndex: 0,
            siblings: ['0x' . str_repeat('a', 64)], // Only 1 sibling instead of 32
            pathIndices: [0],
        );

        $isValid = $this->service->verifyCommitment('0x' . str_repeat('1', 64), $path);

        $this->assertFalse($isValid);
    }

    public function test_sync_tree_clears_cache_and_returns_fresh_root(): void
    {
        // Get initial root
        $initialRoot = $this->service->getMerkleRoot('polygon');

        // Wait a moment to ensure different timestamp
        usleep(100000); // 100ms

        // Sync and get new root
        $syncedRoot = $this->service->syncTree('polygon');

        $this->assertInstanceOf(MerkleRoot::class, $syncedRoot);
        $this->assertEquals('polygon', $syncedRoot->network);
        // The synced_at should be newer
        $this->assertGreaterThanOrEqual(
            $initialRoot->syncedAt->getTimestamp(),
            $syncedRoot->syncedAt->getTimestamp()
        );
    }

    public function test_commitment_normalization_handles_case_insensitivity(): void
    {
        // Add a new commitment with uppercase
        $upperCommitment = '0x' . str_repeat('A', 64);
        $lowerCommitment = '0x' . str_repeat('a', 64);

        // Add the commitment (will be stored normalized to lowercase)
        $addedIndex = $this->service->addDemoCommitment($upperCommitment, 'polygon');

        // Both uppercase and lowercase lookups should return the same path
        $path1 = $this->service->getMerklePath($upperCommitment, 'polygon');
        $path2 = $this->service->getMerklePath($lowerCommitment, 'polygon');

        $this->assertEquals($path1->commitment, $path2->commitment);
        $this->assertEquals($path1->leafIndex, $path2->leafIndex);
        $this->assertEquals($addedIndex, $path1->leafIndex);
    }

    public function test_commitment_normalization_handles_missing_prefix(): void
    {
        // Add commitment without 0x prefix
        $commitmentNoPrefix = str_repeat('e', 64);
        $leafIndex = $this->service->addDemoCommitment($commitmentNoPrefix, 'polygon');

        // Should be retrievable with 0x prefix (normalized form)
        $path = $this->service->getMerklePath('0x' . $commitmentNoPrefix, 'polygon');

        $this->assertEquals($leafIndex, $path->leafIndex);
        $this->assertEquals('0x' . $commitmentNoPrefix, $path->commitment);
    }
}
