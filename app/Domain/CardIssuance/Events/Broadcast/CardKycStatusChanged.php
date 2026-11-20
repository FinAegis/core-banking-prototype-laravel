<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A cardholder's identity-check status changed (any provider). Broadcast as
 * `cards.kyc.status_changed` on the user's private channel.
 */
final class CardKycStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $provider,
        public readonly string $kycStatus,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("user.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'cards.kyc.status_changed';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['provider' => $this->provider, 'kyc_status' => $this->kycStatus];
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('websocket.enabled', true);
    }
}
