<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Privacy\Events\Broadcast;

use App\Domain\Privacy\Events\Broadcast\MerkleRootUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\TestCase;

/**
 * Tests for MerkleRootUpdated broadcast event.
 */
class MerkleRootUpdatedTest extends TestCase
{
    private MerkleRootUpdated $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = new MerkleRootUpdated(
            network: 'polygon',
            merkleRoot: '0x' . str_repeat('a', 64),
            leafCount: 1000,
            blockNumber: 55000000,
            treeDepth: 32,
            syncedAt: '2026-02-02T12:00:00+00:00',
        );
    }

    public function test_implements_should_broadcast(): void
    {
        $this->assertInstanceOf(ShouldBroadcast::class, $this->event);
    }

    public function test_broadcast_on_returns_private_channel(): void
    {
        $channels = $this->event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
    }

    public function test_broadcast_on_includes_network_in_channel_name(): void
    {
        $channels = $this->event->broadcastOn();
        $channel = $channels[0];

        // PrivateChannel's __toString returns 'private-{name}'
        $channelString = (string) $channel;

        $this->assertEquals('private-privacy.merkle.polygon', $channelString);
    }

    public function test_broadcast_as_returns_merkle_updated(): void
    {
        $this->assertEquals('merkle.updated', $this->event->broadcastAs());
    }

    public function test_broadcast_with_returns_expected_data(): void
    {
        $data = $this->event->broadcastWith();

        $this->assertArrayHasKey('network', $data);
        $this->assertArrayHasKey('merkle_root', $data);
        $this->assertArrayHasKey('leaf_count', $data);
        $this->assertArrayHasKey('block_number', $data);
        $this->assertArrayHasKey('tree_depth', $data);
        $this->assertArrayHasKey('synced_at', $data);

        $this->assertEquals('polygon', $data['network']);
        $this->assertEquals('0x' . str_repeat('a', 64), $data['merkle_root']);
        $this->assertEquals(1000, $data['leaf_count']);
        $this->assertEquals(55000000, $data['block_number']);
        $this->assertEquals(32, $data['tree_depth']);
        $this->assertEquals('2026-02-02T12:00:00+00:00', $data['synced_at']);
    }

    public function test_broadcast_when_respects_websocket_config(): void
    {
        config(['websocket.enabled' => true]);
        $this->assertTrue($this->event->broadcastWhen());

        config(['websocket.enabled' => false]);
        $this->assertFalse($this->event->broadcastWhen());
    }

    public function test_broadcast_connection_returns_configured_connection(): void
    {
        config(['websocket.queue.connection' => 'redis']);
        $this->assertEquals('redis', $this->event->broadcastConnection());

        config(['websocket.queue.connection' => 'sync']);
        $this->assertEquals('sync', $this->event->broadcastConnection());
    }

    public function test_broadcast_queue_returns_configured_queue(): void
    {
        config(['websocket.queue.name' => 'broadcasts']);
        $this->assertEquals('broadcasts', $this->event->broadcastQueue());

        config(['websocket.queue.name' => 'realtime']);
        $this->assertEquals('realtime', $this->event->broadcastQueue());
    }

    public function test_different_networks_broadcast_to_different_channels(): void
    {
        $polygonEvent = new MerkleRootUpdated(
            network: 'polygon',
            merkleRoot: '0x' . str_repeat('a', 64),
            leafCount: 100,
            blockNumber: 1,
            treeDepth: 32,
            syncedAt: '2026-01-01T00:00:00+00:00',
        );

        $arbitrumEvent = new MerkleRootUpdated(
            network: 'arbitrum',
            merkleRoot: '0x' . str_repeat('b', 64),
            leafCount: 200,
            blockNumber: 2,
            treeDepth: 32,
            syncedAt: '2026-01-01T00:00:00+00:00',
        );

        $polygonChannels = $polygonEvent->broadcastOn();
        $arbitrumChannels = $arbitrumEvent->broadcastOn();

        $polygonChannel = (string) $polygonChannels[0];
        $arbitrumChannel = (string) $arbitrumChannels[0];

        $this->assertNotEquals($polygonChannel, $arbitrumChannel);
        $this->assertEquals('private-privacy.merkle.polygon', $polygonChannel);
        $this->assertEquals('private-privacy.merkle.arbitrum', $arbitrumChannel);
    }

    public function test_event_is_dispatchable(): void
    {
        $this->assertTrue(
            method_exists(MerkleRootUpdated::class, 'dispatch'),
            'Event should use Dispatchable trait'
        );
    }
}
