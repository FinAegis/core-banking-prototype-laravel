<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing an Agent's unique identifier.
 * Immutable by design.
 */
final class AgentIdentifier
{
    private string $agentId;

    private string $did;

    public function __construct(string $agentId, string $did)
    {
        $this->validateAgentId($agentId);
        $this->validateDid($did);

        $this->agentId = $agentId;
        $this->did = $did;
    }

    private function validateAgentId(string $agentId): void
    {
        if (empty($agentId)) {
            throw new InvalidArgumentException('Agent ID cannot be empty');
        }

        if (strlen($agentId) < 3) {
            throw new InvalidArgumentException('Agent ID must be at least 3 characters long');
        }
    }

    private function validateDid(string $did): void
    {
        if (empty($did)) {
            throw new InvalidArgumentException('DID cannot be empty');
        }

        // DID should follow the pattern: did:method:identifier
        if (! preg_match('/^did:[a-z0-9]+:.+$/i', $did)) {
            throw new InvalidArgumentException('Invalid DID format. Expected: did:method:identifier');
        }
    }

    public function getAgentId(): string
    {
        return $this->agentId;
    }

    public function getDid(): string
    {
        return $this->did;
    }

    public function equals(self $other): bool
    {
        return $this->agentId === $other->agentId && $this->did === $other->did;
    }

    public function toString(): string
    {
        return "{$this->agentId}:{$this->did}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    /**
     * Create from array for reconstitution.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['agent_id'] ?? '',
            $data['did'] ?? ''
        );
    }

    /**
     * Convert to array for persistence.
     */
    public function toArray(): array
    {
        return [
            'agent_id' => $this->agentId,
            'did'      => $this->did,
        ];
    }
}
