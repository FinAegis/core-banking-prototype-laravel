<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when a FinCard funding account's balance changes (a crypto deposit
 * credits, or a withdrawal debits). Delivered on the user's private channel as
 * `fincard.account.funded`. All amounts are integer minor units (USD cents).
 *
 * @see docs/FINCARD_MOBILE_INTEGRATION.md §6
 */
final class FinCardAccountFunded implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $accountId,
        public readonly int $balanceCents,
        public readonly ?int $creditedCents = null,
        public readonly ?string $coinKey = null,
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
        return 'fincard.account.funded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_filter([
            'account_id'     => $this->accountId,
            'balance_cents'  => $this->balanceCents,
            'credited_cents' => $this->creditedCents,
            'coin_key'       => $this->coinKey,
        ], static fn ($v): bool => $v !== null);
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('websocket.enabled', true);
    }
}
