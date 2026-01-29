<?php

declare(strict_types=1);

namespace App\Domain\Account\Events\Broadcast;

use App\Broadcasting\TenantBroadcastEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for balance updates.
 *
 * Fired when an account balance changes.
 * Includes available, pending, and reserved balances.
 */
class BalanceUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use TenantBroadcastEvent;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $accountId,
        public readonly string $accountName,
        public readonly string $accountType,
        public readonly string $totalBalance,
        public readonly string $availableBalance,
        public readonly string $pendingBalance,
        public readonly string $reservedBalance,
        public readonly string $currency,
        public readonly string $previousTotalBalance,
        public readonly string $changeAmount,
        public readonly string $changeReason,
        public readonly ?string $transactionId,
        public readonly string $timestamp,
    ) {
    }

    protected function tenantChannelSuffix(): string
    {
        return 'accounts';
    }

    public function broadcastAs(): string
    {
        return 'balance.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'account_id'             => $this->accountId,
            'account_name'           => $this->accountName,
            'account_type'           => $this->accountType,
            'total_balance'          => $this->totalBalance,
            'available_balance'      => $this->availableBalance,
            'pending_balance'        => $this->pendingBalance,
            'reserved_balance'       => $this->reservedBalance,
            'currency'               => $this->currency,
            'previous_total_balance' => $this->previousTotalBalance,
            'change_amount'          => $this->changeAmount,
            'change_reason'          => $this->changeReason,
            'transaction_id'         => $this->transactionId,
            'timestamp'              => $this->timestamp,
        ];
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
