<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Event fired when a hardware wallet signing operation is completed.
 */
class HardwareWalletSigningCompleted extends ShouldBeStored
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $associationId,
        public readonly string $userId,
        public readonly string $signature,
        public readonly string $publicKey,
        public readonly string $transactionHash,
        public readonly string $chain,
        public readonly bool $success,
        public readonly ?string $errorMessage = null,
        public readonly array $metadata = []
    ) {
    }
}
