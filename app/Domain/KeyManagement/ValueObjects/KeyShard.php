<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\ValueObjects;

use App\Domain\KeyManagement\Enums\ShardType;
use JsonSerializable;

final readonly class KeyShard implements JsonSerializable
{
    public function __construct(
        public ShardType $type,
        public string $data,
        public string $encryptedFor,
        public string $userId,
        public ?int $index = null
    ) {
    }

    public function isHsmEncrypted(): bool
    {
        return $this->type->isHsmStored();
    }

    public function requiresPassword(): bool
    {
        return $this->type->requiresPassword();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type'          => $this->type->value,
            'encrypted_for' => $this->encryptedFor,
            'user_id'       => $this->userId,
            'index'         => $this->index,
            'data_hash'     => hash('sha256', $this->data),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            type: ShardType::from($data['type']),
            data: $data['data'],
            encryptedFor: $data['encrypted_for'],
            userId: $data['user_id'],
            index: $data['index'] ?? null
        );
    }
}
