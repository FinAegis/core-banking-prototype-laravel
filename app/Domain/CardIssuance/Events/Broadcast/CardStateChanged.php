<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A card changed status (any provider). Broadcast
 * as `card.state_changed` on the user's private channel with the card id
 * and its new status.
 */
final class CardStateChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $cardId,
        public readonly string $status,
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
        return 'card.state_changed';
    }

    /**
     * @return array<string, string>
     */
    public function broadcastWith(): array
    {
        return ['card_id' => $this->cardId, 'status' => $this->status];
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('websocket.enabled', true);
    }
}
