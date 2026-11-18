<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Events\Broadcast;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast to the mobile app when a FinCard cardholder's KYC state changes
 * (driven by FinCard cardholder webhooks). Delivered on the user's existing
 * private channel as `fincard.kyc.status_changed`.
 *
 * @see docs/FINCARD_MOBILE_INTEGRATION.md §6
 */
final class FinCardKycStatusChanged implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $cardholderId,
        public readonly string $kycStatus,
        public readonly ?string $kycStage = null,
        public readonly ?string $rejectionReason = null,
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
        return 'fincard.kyc.status_changed';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return array_filter([
            'cardholder_id'    => $this->cardholderId,
            'kyc_status'       => $this->kycStatus,
            'kyc_stage'        => $this->kycStage,
            'rejection_reason' => $this->rejectionReason,
        ], static fn ($v): bool => $v !== null);
    }

    public function broadcastWhen(): bool
    {
        return (bool) config('websocket.enabled', true);
    }
}
