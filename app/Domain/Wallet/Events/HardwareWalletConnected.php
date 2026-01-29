<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

/**
 * Event fired when a hardware wallet is connected/registered.
 */
class HardwareWalletConnected extends ShouldBeStored
{
    /**
     * @param  array<string>  $supportedChains
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $associationId,
        public readonly string $userId,
        public readonly string $deviceType,
        public readonly string $deviceId,
        public readonly string $publicKey,
        public readonly string $address,
        public readonly string $chain,
        public readonly string $derivationPath,
        public readonly array $supportedChains,
        public readonly ?string $deviceLabel = null,
        public readonly ?string $firmwareVersion = null,
        public readonly array $metadata = []
    ) {
    }
}
