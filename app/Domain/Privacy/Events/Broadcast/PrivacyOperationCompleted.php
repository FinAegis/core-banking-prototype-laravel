<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for privacy operations (shield/unshield/transfer).
 *
 * Mobile clients subscribe to user-specific privacy channels to receive
 * real-time status updates when privacy pool operations complete.
 *
 * Channel: private-privacy.{userId}
 * Event name: privacy.operation.completed
 */
class PrivacyOperationCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $operation,
        public readonly string $token,
        public readonly string $amount,
        public readonly string $network,
        public readonly string $status,
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
            new PrivateChannel("privacy.{$this->userId}"),
        ];
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'privacy.operation.completed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'operation' => $this->operation,
            'token'     => $this->token,
            'amount'    => $this->amount,
            'network'   => $this->network,
            'status'    => $this->status,
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
}
