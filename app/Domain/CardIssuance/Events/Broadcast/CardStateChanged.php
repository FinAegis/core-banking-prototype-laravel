<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a FinCard card's state changes (created, frozen, cancelled, or
 * balance moved). Delivered on the user's private channel as `card.state_changed`.
 * `balanceCents` is integer minor units (USD cents).
 *
 * @see docs/FINCARD_MOBILE_INTEGRATION.md §6
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
        public readonly int $balanceCents,
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
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'card_id'       => $this->cardId,
            'status'        => $this->status,
            'balance_cents' => $this->balanceCents,
        ];
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('websocket.enabled', true);
    }
}
