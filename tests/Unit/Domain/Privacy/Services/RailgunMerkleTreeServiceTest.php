<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\Services;

use App\Domain\Privacy\Exceptions\CommitmentNotFoundException;
use App\Domain\Privacy\Services\RailgunBridgeClient;
use App\Domain\Privacy\Services\RailgunMerkleTreeService;
use App\Domain\Privacy\ValueObjects\MerklePath;
use App\Domain\Privacy\ValueObjects\MerkleRoot;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class RailgunMerkleTreeServiceTest extends TestCase
{
    /** @var RailgunBridgeClient&MockInterface */
    private RailgunBridgeClient $bridge;

    private RailgunMerkleTreeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var RailgunBridgeClient&MockInterface $bridge */
        $bridge = Mockery::mock(RailgunBridgeClient::class);
        $this->bridge = $bridge;
        $this->service = new RailgunMerkleTreeService($this->bridge);
    }

    public function test_get_provider_name(): void
    {
        $this->assertEquals('railgun', $this->service->getProviderName());
    }

    public function test_get_supported_networks(): void
    {
        $networks = $this->service->getSupportedNetworks();

        $this->assertContains('ethereum', $networks);
        $this->assertContains('polygon', $networks);
        $this->assertContains('arbitrum', $networks);
        $this->assertContains('bsc', $networks);
        $this->assertNotContains('base', $networks);
    }

    public function test_supports_network(): void
    {
        $this->assertTrue($this->service->supportsNetwork('polygon'));
        $this->assertTrue($this->service->supportsNetwork('ethereum'));
        $this->assertFalse($this->service->supportsNetwork('base'));
        $this->assertFalse($this->service->supportsNetwork('solana'));
    }

    public function test_get_tree_depth(): void
    {
        $this->assertEquals(32, $this->service->getTreeDepth());
    }

    public function test_get_merkle_root(): void
    {
        $this->bridge
            ->shouldReceive('getMerkleRoot')
            ->with('polygon')
            ->once()
            ->andReturn([
                'root'         => '0xabc123',
                'network'      => 'polygon',
                'leaf_count'   => 42000,
                'tree_depth'   => 32,
                'block_number' => 50000000,
                'synced_at'    => '2026-02-27T12:00:00Z',
            ]);

        $root = $this->service->getMerkleRoot('polygon');

        $this->assertInstanceOf(MerkleRoot::class, $root);
        $this->assertEquals('0xabc123', $root->root);
        $this->assertEquals('polygon', $root->network);
        $this->assertEquals(42000, $root->leafCount);
        $this->assertEquals(32, $root->treeDepth);
    }

    public function test_get_merkle_root_throws_for_unsupported_network(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not supported by RAILGUN');

        $this->service->getMerkleRoot('base');
    }

    public function test_get_merkle_path(): void
    {
        $this->bridge
            ->shouldReceive('getMerkleProof')
            ->with('0xcommit', 'polygon')
            ->once()
            ->andReturn([
                'commitment'   => '0xcommit',
                'root'         => '0xroot',
                'network'      => 'polygon',
                'leaf_index'   => 42,
                'siblings'     => ['0xs1', '0xs2'],
                'path_indices' => [0, 1],
            ]);

        $path = $this->service->getMerklePath('0xcommit', 'polygon');

        $this->assertInstanceOf(MerklePath::class, $path);
        $this->assertEquals('0xcommit', $path->commitment);
        $this->assertEquals('0xroot', $path->root);
    }

    public function test_get_merkle_path_throws_commitment_not_found(): void
    {
        $this->bridge
            ->shouldReceive('getMerkleProof')
            ->with('0xbadcommit', 'polygon')
            ->once()
            ->andThrow(new RuntimeException('Not found'));

        $this->expectException(CommitmentNotFoundException::class);

        $this->service->getMerklePath('0xbadcommit', 'polygon');
    }

    public function test_verify_commitment_returns_true(): void
    {
        $this->bridge
            ->shouldReceive('getMerkleProof')
            ->with('0xcommit', 'polygon')
            ->once()
            ->andReturn(['verified' => true]);

        $path = new MerklePath('0xcommit', '0xroot', 'polygon', 0, [], []);

        $this->assertTrue($this->service->verifyCommitment('0xcommit', $path));
    }

    public function test_verify_commitment_returns_false_on_failure(): void
    {
        $this->bridge
            ->shouldReceive('getMerkleProof')
            ->with('0xcommit', 'polygon')
            ->once()
            ->andThrow(new RuntimeException('Bridge error'));

        $path = new MerklePath('0xcommit', '0xroot', 'polygon', 0, [], []);

        $this->assertFalse($this->service->verifyCommitment('0xcommit', $path));
    }

    public function test_sync_tree(): void
    {
        $this->bridge
            ->shouldReceive('scanWallet')
            ->with('system', 'polygon')
            ->once()
            ->andThrow(new RuntimeException('No system wallet'));

        $this->bridge
            ->shouldReceive('getMerkleRoot')
            ->with('polygon')
            ->once()
            ->andReturn([
                'root'       => '0xnewroot',
                'network'    => 'polygon',
                'leaf_count' => 43000,
                'tree_depth' => 32,
                'synced_at'  => '2026-02-27T12:30:00Z',
            ]);

        $root = $this->service->syncTree('polygon');

        $this->assertInstanceOf(MerkleRoot::class, $root);
        $this->assertEquals('0xnewroot', $root->root);
    }
}
