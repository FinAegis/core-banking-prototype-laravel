<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A withdrawal from a card moved to another status, or a new code was sent.
 * Delivered on the user's private channel as `card.withdrawal.updated`.
 * `receivedAmount` (USDC, 2 decimals) is set once the money is in the wallet;
 * `codeExpiresAt` while a code is waiting. The code itself never travels here.
 */
final class CardWithdrawalUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $withdrawalId,
        public readonly string $cardId,
        public readonly string $status,
        public readonly ?string $receivedAmount,
        public readonly ?string $codeExpiresAt,
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
        return 'card.withdrawal.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'withdrawal_id'   => $this->withdrawalId,
            'card_id'         => $this->cardId,
            'status'          => $this->status,
            'received_amount' => $this->receivedAmount,
            'code_expires_at' => $this->codeExpiresAt,
        ];
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('websocket.enabled', true);
    }
}
