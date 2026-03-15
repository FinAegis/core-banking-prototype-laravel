<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user's shielded (privacy pool) balance changes.
 *
 * Fires alongside PrivacyOperationCompleted after shield/unshield/transfer.
 * Mobile clients invalidate their shielded balance cache.
 *
 * Channel: private-privacy.{userId}
 * Event name: privacy.balance_updated
 */
class PrivacyBalanceUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly ?string $chainId = null,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("privacy.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'privacy.balance_updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $data = [];
        if ($this->chainId !== null) {
            $data['chain_id'] = $this->chainId;
        }

        return $data;
    }

    public function broadcastWhen(): bool
    {
        return config('websocket.enabled', true);
    }

    public function broadcastConnection(): string
    {
        return config('websocket.queue.connection', 'redis');
    }

    public function broadcastQueue(): string
    {
        return config('websocket.queue.name', 'broadcasts');
    }
}
