<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\Enums\TokenType;
use DateTimeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a soulbound token is issued.
 */
class SoulboundTokenIssued
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tokenId,
        public readonly TokenType $tokenType,
        public readonly string $issuerId,
        public readonly string $recipientId,
        public readonly DateTimeInterface $issuedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token_id'     => $this->tokenId,
            'token_type'   => $this->tokenType->value,
            'issuer_id'    => $this->issuerId,
            'recipient_id' => $this->recipientId,
            'issued_at'    => $this->issuedAt->format('c'),
        ];
    }
}
