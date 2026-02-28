<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for confirmed commerce payments.
 *
 * Merchants subscribe to their commerce channel to receive real-time
 * payment attestation confirmations.
 *
 * Channel: private-commerce.{merchantId}
 * Event name: commerce.payment.confirmed
 */
class CommercePaymentConfirmed implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $merchantId,
        public readonly string $attestationId,
        public readonly string $attestationType,
        public readonly string $attestationHash,
        public readonly string $attestedAt,
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
            new PrivateChannel("commerce.{$this->merchantId}"),
        ];
    }

    /**
     * Get the event name for broadcasting.
     */
    public function broadcastAs(): string
    {
        return 'commerce.payment.confirmed';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'attestation_id'   => $this->attestationId,
            'attestation_type' => $this->attestationType,
            'attestation_hash' => $this->attestationHash,
            'attested_at'      => $this->attestedAt,
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
