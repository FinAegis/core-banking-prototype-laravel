<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use DateTimeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a soulbound token is revoked.
 */
class SoulboundTokenRevoked
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tokenId,
        public readonly string $reason,
        public readonly DateTimeInterface $revokedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token_id'   => $this->tokenId,
            'reason'     => $this->reason,
            'revoked_at' => $this->revokedAt->format('c'),
        ];
    }
}
