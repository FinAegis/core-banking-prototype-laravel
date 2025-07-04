<?php

namespace App\Domain\Wallet\ValueObjects;

class AddressData
{
    public function __construct(
        public readonly string $address,
        public readonly string $publicKey,
        public readonly string $chain,
        public readonly ?string $derivationPath = null,
        public readonly array $metadata = []
    ) {}
    
    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'public_key' => $this->publicKey,
            'chain' => $this->chain,
            'derivation_path' => $this->derivationPath,
            'metadata' => $this->metadata,
        ];
    }
}