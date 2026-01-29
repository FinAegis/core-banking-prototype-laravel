<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Event fired when a signing request is created for a hardware wallet.
 */
class HardwareWalletSigningRequested extends ShouldBeStored
{
    /**
     * @param  array<string, mixed>  $transactionData
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $associationId,
        public readonly string $userId,
        public readonly string $chain,
        public readonly array $transactionData,
        public readonly string $rawDataToSign,
        public readonly string $expiresAt,
        public readonly array $metadata = []
    ) {
    }
}
