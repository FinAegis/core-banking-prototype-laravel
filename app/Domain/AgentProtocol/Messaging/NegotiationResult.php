<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Messaging;

/**
 * Result of a protocol negotiation attempt.
 */
final class NegotiationResult
{
    private function __construct(
        public readonly bool $success,
        public readonly ?ProtocolAgreement $agreement,
        public readonly ?string $error,
        public readonly bool $timeout,
        public readonly bool $existingAgreement,
        public readonly ?string $initiatorDid,
        public readonly ?string $targetDid
    ) {
    }

    /**
     * Create a successful negotiation result.
     */
    public static function success(ProtocolAgreement $agreement): self
    {
        return new self(
            success: true,
            agreement: $agreement,
            error: null,
            timeout: false,
            existingAgreement: false,
            initiatorDid: $agreement->initiatorDid,
            targetDid: $agreement->responderDid
        );
    }

    /**
     * Create a result from an existing agreement.
     */
    public static function fromExisting(ProtocolAgreement $agreement): self
    {
        return new self(
            success: true,
            agreement: $agreement,
            error: null,
            timeout: false,
            existingAgreement: true,
            initiatorDid: $agreement->initiatorDid,
            targetDid: $agreement->responderDid
        );
    }

    /**
     * Create a timeout result.
     */
    public static function timeout(string $initiatorDid, string $targetDid): self
    {
        return new self(
            success: false,
            agreement: null,
            error: 'Negotiation timed out',
            timeout: true,
            existingAgreement: false,
            initiatorDid: $initiatorDid,
            targetDid: $targetDid
        );
    }

    /**
     * Create a rejected result.
     */
    public static function rejected(string $initiatorDid, string $targetDid, string $reason): self
    {
        return new self(
            success: false,
            agreement: null,
            error: $reason,
            timeout: false,
            existingAgreement: false,
            initiatorDid: $initiatorDid,
            targetDid: $targetDid
        );
    }

    /**
     * Check if negotiation was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if this used an existing agreement.
     */
    public function usedExistingAgreement(): bool
    {
        return $this->existingAgreement;
    }

    /**
     * Get the negotiated protocol version.
     */
    public function getVersion(): ?string
    {
        return $this->agreement?->version;
    }

    /**
     * Convert to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success'           => $this->success,
            'agreement'         => $this->agreement?->toArray(),
            'error'             => $this->error,
            'timeout'           => $this->timeout,
            'existingAgreement' => $this->existingAgreement,
            'initiatorDid'      => $this->initiatorDid,
            'targetDid'         => $this->targetDid,
        ];
    }
}
