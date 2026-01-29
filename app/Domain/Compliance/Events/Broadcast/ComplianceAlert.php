<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Events\Broadcast;

use App\Broadcasting\TenantBroadcastEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast event for compliance alerts.
 *
 * Fired when compliance events occur that require admin attention.
 * Only broadcast to admin-authorized channels.
 */
class ComplianceAlert implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use TenantBroadcastEvent;

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    public const TYPE_AML_ALERT = 'aml_alert';

    public const TYPE_KYC_REQUIRED = 'kyc_required';

    public const TYPE_TRANSACTION_REVIEW = 'transaction_review';

    public const TYPE_THRESHOLD_EXCEEDED = 'threshold_exceeded';

    public const TYPE_SUSPICIOUS_ACTIVITY = 'suspicious_activity';

    public const TYPE_SANCTIONS_MATCH = 'sanctions_match';

    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $alertId,
        public readonly string $alertType,
        public readonly string $severity,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $entityType,
        public readonly ?string $entityId,
        public readonly ?string $userId,
        public readonly ?string $accountId,
        public readonly ?string $transactionId,
        public readonly array $details,
        public readonly bool $requiresAction,
        public readonly ?string $actionUrl,
        public readonly string $timestamp,
    ) {
    }

    protected function tenantChannelSuffix(): string
    {
        return 'compliance';
    }

    public function broadcastAs(): string
    {
        return match ($this->alertType) {
            self::TYPE_AML_ALERT, self::TYPE_SANCTIONS_MATCH, self::TYPE_SUSPICIOUS_ACTIVITY => 'alert.created',
            self::TYPE_KYC_REQUIRED       => 'review.required',
            self::TYPE_THRESHOLD_EXCEEDED => 'threshold.exceeded',
            default                       => 'alert.created',
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
            'alert_id'        => $this->alertId,
            'alert_type'      => $this->alertType,
            'severity'        => $this->severity,
            'title'           => $this->title,
            'message'         => $this->message,
            'entity_type'     => $this->entityType,
            'entity_id'       => $this->entityId,
            'user_id'         => $this->userId,
            'account_id'      => $this->accountId,
            'transaction_id'  => $this->transactionId,
            'details'         => $this->details,
            'requires_action' => $this->requiresAction,
            'action_url'      => $this->actionUrl,
            'timestamp'       => $this->timestamp,
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
