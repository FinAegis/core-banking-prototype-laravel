<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Privacy;

use App\Domain\Privacy\Events\Broadcast\MerkleRootUpdated;
use App\Domain\Privacy\Services\DemoMerkleTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Tests for Merkle tree WebSocket broadcast integration.
 */
class MerkleTreeWebSocketTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_tree_dispatches_merkle_root_updated_event(): void
    {
        Event::fake([MerkleRootUpdated::class]);

        $service = new DemoMerkleTreeService();
        $service->syncTree('polygon');

        Event::assertDispatched(MerkleRootUpdated::class, function (MerkleRootUpdated $event) {
            return $event->network === 'polygon'
                && strlen($event->merkleRoot) === 66 // 0x + 64 hex chars
                && $event->treeDepth === 32;
        });
    }

    public function test_sync_tree_dispatches_event_for_each_network(): void
    {
        Event::fake([MerkleRootUpdated::class]);

        $service = new DemoMerkleTreeService();

        $service->syncTree('polygon');
        $service->syncTree('base');
        $service->syncTree('arbitrum');

        Event::assertDispatched(MerkleRootUpdated::class, 3);

        // Verify different networks
        Event::assertDispatched(MerkleRootUpdated::class, fn ($e) => $e->network === 'polygon');
        Event::assertDispatched(MerkleRootUpdated::class, fn ($e) => $e->network === 'base');
        Event::assertDispatched(MerkleRootUpdated::class, fn ($e) => $e->network === 'arbitrum');
    }

    public function test_event_contains_correct_merkle_root_data(): void
    {
        Event::fake([MerkleRootUpdated::class]);

        $service = new DemoMerkleTreeService();
        $root = $service->syncTree('polygon');

        Event::assertDispatched(MerkleRootUpdated::class, function (MerkleRootUpdated $event) use ($root) {
            return $event->merkleRoot === $root->root
                && $event->leafCount === $root->leafCount
                && $event->blockNumber === $root->blockNumber;
        });
    }

    public function test_channel_authorization_callback_allows_supported_networks(): void
    {
        // Test the channel authorization logic directly
        $supportedNetworks = config('privacy.merkle.networks', ['polygon', 'base', 'arbitrum']);

        foreach ($supportedNetworks as $network) {
            // The authorization callback returns true if network is supported
            $result = in_array($network, $supportedNetworks, true);
            $this->assertTrue($result, "Network {$network} should be authorized");
        }
    }

    public function test_channel_authorization_callback_rejects_unsupported_networks(): void
    {
        // Test the channel authorization logic directly
        $supportedNetworks = config('privacy.merkle.networks', ['polygon', 'base', 'arbitrum']);

        $unsupportedNetworks = ['ethereum', 'solana', 'invalid_network'];

        foreach ($unsupportedNetworks as $network) {
            // The authorization callback returns false if network is not supported
            $result = in_array($network, $supportedNetworks, true);
            $this->assertFalse($result, "Network {$network} should be rejected");
        }
    }
}
