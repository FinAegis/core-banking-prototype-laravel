<?php

declare(strict_types=1);

namespace App\Domain\Privacy\ValueObjects;

use App\Domain\Privacy\Enums\ProofType;
use DateTimeImmutable;
use JsonSerializable;

/**
 * Immutable value object representing a zero-knowledge proof.
 */
final readonly class ZkProof implements JsonSerializable
{
    /**
     * @param array<string, mixed> $publicInputs
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public ProofType $type,
        public string $proof,
        public array $publicInputs,
        public string $verifierAddress,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public array $metadata = [],
    ) {
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new DateTimeImmutable();
    }

    public function getRemainingValiditySeconds(): int
    {
        $now = new DateTimeImmutable();
        if ($this->isExpired()) {
            return 0;
        }

        return $this->expiresAt->getTimestamp() - $now->getTimestamp();
    }

    public function getProofHash(): string
    {
        return hash('sha256', $this->proof);
    }

    public function hasPublicInput(string $key): bool
    {
        return isset($this->publicInputs[$key]);
    }

    public function getPublicInput(string $key, mixed $default = null): mixed
    {
        return $this->publicInputs[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type'             => $this->type->value,
            'proof_hash'       => $this->getProofHash(),
            'public_inputs'    => $this->publicInputs,
            'verifier_address' => $this->verifierAddress,
            'created_at'       => $this->createdAt->format('c'),
            'expires_at'       => $this->expiresAt->format('c'),
            'is_expired'       => $this->isExpired(),
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
            type: ProofType::from($data['type']),
            proof: $data['proof'],
            publicInputs: $data['public_inputs'] ?? [],
            verifierAddress: $data['verifier_address'],
            createdAt: new DateTimeImmutable($data['created_at']),
            expiresAt: new DateTimeImmutable($data['expires_at']),
            metadata: $data['metadata'] ?? [],
        );
    }
}
