<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for Merkle tree root updates.
 *
 * Fired when a privacy pool Merkle tree is synced with new commitments.
 * Mobile clients subscribe to network-specific channels to receive real-time
 * Merkle root updates for ZK proof generation.
 *
 * Channel: private-privacy.merkle.{network}
 * Event name: merkle.updated
 */
class MerkleRootUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $network,
        public readonly string $merkleRoot,
        public readonly int $leafCount,
        public readonly int $blockNumber,
        public readonly int $treeDepth,
        public readonly string $syncedAt,
    ) {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("privacy.merkle.{$this->network}"),
        ];
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'merkle.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'network'      => $this->network,
            'merkle_root'  => $this->merkleRoot,
            'leaf_count'   => $this->leafCount,
            'block_number' => $this->blockNumber,
            'tree_depth'   => $this->treeDepth,
            'synced_at'    => $this->syncedAt,
        ];
    }

    /**
     * Determine if the event should be broadcast.
     */
    public function broadcastWhen(): bool
    {
        return config('websocket.enabled', true);
    }

    /**
     * Get the broadcast queue connection.
     */
    public function broadcastConnection(): string
    {
        return config('websocket.queue.connection', 'redis');
    }

    /**
     * Get the broadcast queue.
     */
    public function broadcastQueue(): string
    {
        return config('websocket.queue.name', 'broadcasts');
    }

    /**
     * Create an instance from a MerkleRoot value object.
     *
     * @param \App\Domain\Privacy\ValueObjects\MerkleRoot $merkleRoot
     */
    public static function fromMerkleRoot(mixed $merkleRoot): self
    {
        return new self(
            network: $merkleRoot->network,
            merkleRoot: $merkleRoot->root,
            leafCount: $merkleRoot->leafCount,
            blockNumber: $merkleRoot->blockNumber,
            treeDepth: $merkleRoot->treeDepth,
            syncedAt: $merkleRoot->syncedAt->format('c'),
        );
    }
}
