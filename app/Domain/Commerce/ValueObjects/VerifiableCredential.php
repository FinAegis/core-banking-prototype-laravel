<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use App\Domain\Commerce\Enums\CredentialType;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Represents a W3C Verifiable Credential.
 *
 * Following the W3C VC Data Model for interoperability.
 *
 * @see https://www.w3.org/TR/vc-data-model/
 */
final readonly class VerifiableCredential
{
    public function __construct(
        public string $credentialId,
        public CredentialType $type,
        public string $issuerId,
        public string $subjectId,
        /** @var array<string, mixed> */
        public array $credentialSubject,
        public string $proof,
        public DateTimeInterface $issuedAt,
        public ?DateTimeInterface $expiresAt = null,
        /** @var array<string> */
        public array $context = ['https://www.w3.org/2018/credentials/v1'],
        public ?string $credentialStatus = null,
    ) {
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Get the credential hash.
     */
    public function getCredentialHash(): string
    {
        $data = [
            'credential_id' => $this->credentialId,
            'type'          => $this->type->value,
            'issuer_id'     => $this->issuerId,
            'subject_id'    => $this->subjectId,
            'subject_hash'  => hash('sha256', json_encode($this->credentialSubject, JSON_THROW_ON_ERROR)),
            'issued_at'     => $this->issuedAt->format('c'),
        ];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * Convert to W3C VC format.
     *
     * @return array<string, mixed>
     */
    public function toW3CFormat(): array
    {
        $vc = [
            '@context'          => $this->context,
            'id'                => $this->credentialId,
            'type'              => ['VerifiableCredential', $this->type->vcType()],
            'issuer'            => $this->issuerId,
            'issuanceDate'      => $this->issuedAt->format('c'),
            'credentialSubject' => array_merge(
                ['id' => $this->subjectId],
                $this->credentialSubject,
            ),
            'proof' => [
                'type'               => 'Ed25519Signature2020',
                'created'            => $this->issuedAt->format('c'),
                'verificationMethod' => $this->issuerId . '#key-1',
                'proofPurpose'       => 'assertionMethod',
                'proofValue'         => $this->proof,
            ],
        ];

        if ($this->expiresAt !== null) {
            $vc['expirationDate'] = $this->expiresAt->format('c');
        }

        if ($this->credentialStatus !== null) {
            $vc['credentialStatus'] = [
                'id'   => $this->credentialStatus,
                'type' => 'RevocationList2020Status',
            ];
        }

        return $vc;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'credential_id'      => $this->credentialId,
            'type'               => $this->type->value,
            'issuer_id'          => $this->issuerId,
            'subject_id'         => $this->subjectId,
            'credential_subject' => $this->credentialSubject,
            'proof'              => $this->proof,
            'issued_at'          => $this->issuedAt->format('c'),
            'expires_at'         => $this->expiresAt?->format('c'),
            'context'            => $this->context,
            'credential_status'  => $this->credentialStatus,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            credentialId: $data['credential_id'],
            type: CredentialType::from($data['type']),
            issuerId: $data['issuer_id'],
            subjectId: $data['subject_id'],
            credentialSubject: $data['credential_subject'],
            proof: $data['proof'],
            issuedAt: new DateTimeImmutable($data['issued_at']),
            expiresAt: isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null,
            context: $data['context'] ?? ['https://www.w3.org/2018/credentials/v1'],
            credentialStatus: $data['credential_status'] ?? null,
        );
    }
}
