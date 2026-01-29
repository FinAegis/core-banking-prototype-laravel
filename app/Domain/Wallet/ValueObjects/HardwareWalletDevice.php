<?php

declare(strict_types=1);

namespace App\Domain\Wallet\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing a hardware wallet device.
 */
final readonly class HardwareWalletDevice
{
    public const TYPE_LEDGER_NANO_S = 'ledger_nano_s';

    public const TYPE_LEDGER_NANO_X = 'ledger_nano_x';

    public const TYPE_TREZOR_ONE = 'trezor_one';

    public const TYPE_TREZOR_MODEL_T = 'trezor_model_t';

    public const SUPPORTED_TYPES = [
        self::TYPE_LEDGER_NANO_S,
        self::TYPE_LEDGER_NANO_X,
        self::TYPE_TREZOR_ONE,
        self::TYPE_TREZOR_MODEL_T,
    ];

    /**
     * @param  array<string>  $supportedChains
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        public string $type,
        public string $deviceId,
        public string $label,
        public string $firmwareVersion,
        public array $supportedChains,
        public ?string $publicKey = null,
        public ?string $address = null,
        public array $metadata = []
    ) {
    }

    /**
     * Create a new hardware wallet device instance.
     *
     * @param  array<string>  $supportedChains
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        string $type,
        string $deviceId,
        string $label,
        string $firmwareVersion,
        array $supportedChains,
        ?string $publicKey = null,
        ?string $address = null,
        array $metadata = []
    ): self {
        if (! in_array($type, self::SUPPORTED_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported hardware wallet type: {$type}");
        }

        if (empty($deviceId)) {
            throw new InvalidArgumentException('Device ID cannot be empty');
        }

        return new self(
            $type,
            $deviceId,
            $label,
            $firmwareVersion,
            $supportedChains,
            $publicKey,
            $address,
            $metadata
        );
    }

    /**
     * Create from array data (e.g., from database).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: $data['type'],
            deviceId: $data['device_id'],
            label: $data['label'] ?? '',
            firmwareVersion: $data['firmware_version'] ?? '',
            supportedChains: $data['supported_chains'] ?? [],
            publicKey: $data['public_key'] ?? null,
            address: $data['address'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function isLedger(): bool
    {
        return in_array($this->type, [self::TYPE_LEDGER_NANO_S, self::TYPE_LEDGER_NANO_X], true);
    }

    public function isTrezor(): bool
    {
        return in_array($this->type, [self::TYPE_TREZOR_ONE, self::TYPE_TREZOR_MODEL_T], true);
    }

    public function supportsChain(string $chain): bool
    {
        return in_array($chain, $this->supportedChains, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type'             => $this->type,
            'device_id'        => $this->deviceId,
            'label'            => $this->label,
            'firmware_version' => $this->firmwareVersion,
            'supported_chains' => $this->supportedChains,
            'public_key'       => $this->publicKey,
            'address'          => $this->address,
            'metadata'         => $this->metadata,
        ];
    }
}
