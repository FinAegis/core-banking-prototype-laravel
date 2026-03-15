<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a user's wallet state changes.
 *
 * Triggers: wallet activation, smart account deployment, recovery shard backup.
 * Mobile clients invalidate wallet state cache and re-fetch on demand.
 *
 * Channel: private-wallet.{userId}
 * Event name: wallet.state_changed
 */
class WalletStateChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("wallet.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'wallet.state_changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [];
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
