<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A card top-up moved to another status (any provider). Delivered on the
 * user's private channel as `card.topup.updated`.
 * `creditedCents` is set once the money is on the card.
 */
final class CardTopUpUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $topupId,
        public readonly ?string $cardId,
        public readonly string $status,
        public readonly ?int $creditedCents,
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
        return 'card.topup.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'topup_id'       => $this->topupId,
            'card_id'        => $this->cardId,
            'status'         => $this->status,
            'credited_cents' => $this->creditedCents,
        ];
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('websocket.enabled', true);
    }
}
