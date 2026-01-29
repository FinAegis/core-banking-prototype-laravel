<?php

declare(strict_types=1);

namespace App\Domain\Account\Events\Broadcast;

use App\Broadcasting\TenantBroadcastEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for transaction notifications.
 *
 * Fired when a credit or debit transaction occurs on an account.
 * Provides real-time notification to account holders.
 */
class TransactionNotification implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use TenantBroadcastEvent;

    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $transactionId,
        public readonly string $accountId,
        public readonly string $accountName,
        public readonly string $type,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $description,
        public readonly string $status,
        public readonly ?string $referenceNumber,
        public readonly ?string $counterparty,
        public readonly array $metadata,
        public readonly string $timestamp,
    ) {
    }

    protected function tenantChannelSuffix(): string
    {
        return 'transactions';
    }

    public function broadcastAs(): string
    {
        return match ($this->type) {
            self::TYPE_CREDIT => 'transaction.credited',
            self::TYPE_DEBIT  => 'transaction.debited',
            default           => 'transaction.pending',
        };
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'transaction_id'   => $this->transactionId,
            'account_id'       => $this->accountId,
            'account_name'     => $this->accountName,
            'type'             => $this->type,
            'amount'           => $this->amount,
            'currency'         => $this->currency,
            'description'      => $this->description,
            'status'           => $this->status,
            'reference_number' => $this->referenceNumber,
            'counterparty'     => $this->counterparty,
            'metadata'         => $this->metadata,
            'timestamp'        => $this->timestamp,
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
