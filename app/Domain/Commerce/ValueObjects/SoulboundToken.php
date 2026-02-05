<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use App\Domain\Commerce\Enums\TokenType;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Represents a soulbound token (SBT) - a non-transferable token bound to an identity.
 *
 * Soulbound tokens are used for:
 * - Verifiable credentials
 * - Membership attestations
 * - Reputation scores
 * - Identity verification badges
 */
final readonly class SoulboundToken
{
    public function __construct(
        public string $tokenId,
        public TokenType $type,
        public string $issuerId,
        public string $recipientId,
        /** @var array<string, mixed> */
        public array $metadata,
        public DateTimeInterface $issuedAt,
        public ?DateTimeInterface $expiresAt = null,
        public ?DateTimeInterface $revokedAt = null,
        public ?string $revocationReason = null,
    ) {
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isRevoked();
    }

    public function getRemainingValiditySeconds(): int
    {
        if ($this->expiresAt === null) {
            return PHP_INT_MAX;
        }

        $remaining = $this->expiresAt->getTimestamp() - time();

        return max(0, $remaining);
    }

    /**
     * Get the token hash for verification purposes.
     *
     * Uses HMAC with full field coverage to prevent undetected modification.
     */
    public function getTokenHash(): string
    {
        $data = [
            'token_id'     => $this->tokenId,
            'type'         => $this->type->value,
            'issuer_id'    => $this->issuerId,
            'recipient_id' => $this->recipientId,
            'issued_at'    => $this->issuedAt->format('c'),
            'metadata'     => $this->metadata,
        ];

        if ($this->expiresAt !== null) {
            $data['expires_at'] = $this->expiresAt->format('c');
        }

        return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), config('app.key'));
    }

    /**
     * Get a specific metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'token_id'          => $this->tokenId,
            'type'              => $this->type->value,
            'issuer_id'         => $this->issuerId,
            'recipient_id'      => $this->recipientId,
            'metadata'          => $this->metadata,
            'issued_at'         => $this->issuedAt->format('c'),
            'expires_at'        => $this->expiresAt?->format('c'),
            'revoked_at'        => $this->revokedAt?->format('c'),
            'revocation_reason' => $this->revocationReason,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tokenId: $data['token_id'],
            type: TokenType::from($data['type']),
            issuerId: $data['issuer_id'],
            recipientId: $data['recipient_id'],
            metadata: $data['metadata'] ?? [],
            issuedAt: new DateTimeImmutable($data['issued_at']),
            expiresAt: isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null,
            revokedAt: isset($data['revoked_at']) ? new DateTimeImmutable($data['revoked_at']) : null,
            revocationReason: $data['revocation_reason'] ?? null,
        );
    }
}
