<?php

declare(strict_types=1);

namespace App\Domain\TrustCert\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for TrustCert certificate status changes.
 *
 * Users subscribe to their trustcert channel to receive real-time
 * certificate issuance and revocation notifications.
 *
 * Channel: private-trustcert.{userId}
 * Event name: trustcert.status.changed
 */
class TrustCertStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly string $certificateId,
        public readonly string $status,
        public readonly string $subjectId,
        public readonly string $changedAt,
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
            new PrivateChannel("trustcert.{$this->userId}"),
        ];
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'trustcert.status.changed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'certificate_id' => $this->certificateId,
            'status'         => $this->status,
            'subject_id'     => $this->subjectId,
            'changed_at'     => $this->changedAt,
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
