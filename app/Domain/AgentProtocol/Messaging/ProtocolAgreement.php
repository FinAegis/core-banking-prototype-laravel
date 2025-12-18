<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Messaging;

use DateTimeImmutable;
use JsonSerializable;

/**
 * Represents a negotiated protocol agreement between two agents.
 */
final class ProtocolAgreement implements JsonSerializable
{
    public function __construct(
        public readonly string $agreementId,
        public readonly string $version,
        public readonly string $initiatorDid,
        public readonly string $responderDid,
        public readonly string $encryptionMethod,
        public readonly string $signatureMethod,
        public readonly array $capabilities,
        public readonly DateTimeImmutable $agreedAt,
        public readonly DateTimeImmutable $expiresAt,
        public readonly array $metadata = []
    ) {
    }

    /**
     * Check if the agreement has expired.
     */
    public function isExpired(): bool
    {
        return new DateTimeImmutable() > $this->expiresAt;
    }

    /**
     * Check if a specific capability is included in the agreement.
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * Check if this agreement involves a specific agent.
     */
    public function involvesAgent(string $agentDid): bool
    {
        return $this->initiatorDid === $agentDid || $this->responderDid === $agentDid;
    }

    /**
     * Get the other party in the agreement.
     */
    public function getOtherParty(string $agentDid): ?string
    {
        if ($this->initiatorDid === $agentDid) {
            return $this->responderDid;
        }

        if ($this->responderDid === $agentDid) {
            return $this->initiatorDid;
        }

        return null;
    }

    /**
     * Get remaining validity time in seconds.
     */
    public function getRemainingValiditySeconds(): int
    {
        $now = new DateTimeImmutable();
        $diff = $this->expiresAt->getTimestamp() - $now->getTimestamp();

        return max(0, $diff);
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'agreementId'      => $this->agreementId,
            'version'          => $this->version,
            'initiatorDid'     => $this->initiatorDid,
            'responderDid'     => $this->responderDid,
            'encryptionMethod' => $this->encryptionMethod,
            'signatureMethod'  => $this->signatureMethod,
            'capabilities'     => $this->capabilities,
            'agreedAt'         => $this->agreedAt->format('c'),
            'expiresAt'        => $this->expiresAt->format('c'),
            'metadata'         => $this->metadata,
        ];
    }

    /**
     * Create from array representation.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            agreementId: $data['agreementId'],
            version: $data['version'],
            initiatorDid: $data['initiatorDid'],
            responderDid: $data['responderDid'],
            encryptionMethod: $data['encryptionMethod'],
            signatureMethod: $data['signatureMethod'],
            capabilities: $data['capabilities'] ?? [],
            agreedAt: new DateTimeImmutable($data['agreedAt']),
            expiresAt: new DateTimeImmutable($data['expiresAt']),
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
