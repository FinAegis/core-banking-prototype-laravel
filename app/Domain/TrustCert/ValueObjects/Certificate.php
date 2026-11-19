<?php

declare(strict_types=1);

namespace App\Domain\TrustCert\ValueObjects;

use App\Domain\TrustCert\Enums\CertificateStatus;
use App\Models\User;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Represents a digital certificate in the trust hierarchy.
 */
final readonly class Certificate
{
    public function __construct(
        public string $certificateId,
        public string $subjectId,
        /** @var array<string, mixed> */
        public array $subject,
        public string $publicKey,
        public string $signature,
        public DateTimeInterface $validFrom,
        public DateTimeInterface $validUntil,
        public CertificateStatus $status,
        public ?string $parentCertificateId = null,
        /** @var array<string, mixed> */
        public array $extensions = [],
        public ?DateTimeInterface $revokedAt = null,
        public ?string $revocationReason = null,
    ) {
    }

    public function isExpired(): bool
    {
        return $this->validUntil < new DateTimeImmutable();
    }

    public function isNotYetValid(): bool
    {
        return $this->validFrom > new DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->status === CertificateStatus::REVOKED;
    }

    public function isSuspended(): bool
    {
        return $this->status === CertificateStatus::SUSPENDED;
    }

    public function isValid(): bool
    {
        return $this->status === CertificateStatus::ACTIVE
            && ! $this->isExpired()
            && ! $this->isNotYetValid();
    }

    public function canSign(): bool
    {
        return $this->isValid();
    }

    public function getRemainingValiditySeconds(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        $remaining = $this->validUntil->getTimestamp() - time();

        return max(0, $remaining);
    }

    public function isRootCertificate(): bool
    {
        return $this->parentCertificateId === null;
    }

    /**
     * Whether the given (authenticated) user holds this certificate:
     * subject "user:{uuid}" (issued on KYC approval), "user:{id}" (the
     * subject the mobile current-certificate lookup uses) or
     * "agent:{agentId}:employer:{id}" (an agent certificate, held by the
     * employer). A missing user never matches.
     */
    public function isHeldBy(?Authenticatable $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $subjectId = $this->subjectId;
        $uuid = (string) ($user->uuid ?? '');
        $id = (string) $user->getKey();

        if ($uuid !== '' && hash_equals('user:' . $uuid, $subjectId)) {
            return true;
        }

        if ($id !== '' && hash_equals('user:' . $id, $subjectId)) {
            return true;
        }

        return $id !== ''
            && preg_match('/^agent:[A-Za-z0-9_\-]+:employer:(\d+)$/', $subjectId, $matches) === 1
            && hash_equals($id, $matches[1]);
    }

    /**
     * Get certificate fingerprint (SHA-256).
     */
    public function getFingerprint(): string
    {
        $data = [
            'certificate_id' => $this->certificateId,
            'subject_id'     => $this->subjectId,
            'public_key'     => $this->publicKey,
            'valid_from'     => $this->validFrom->format('c'),
            'valid_until'    => $this->validUntil->format('c'),
        ];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'certificate_id'        => $this->certificateId,
            'subject_id'            => $this->subjectId,
            'subject'               => $this->subject,
            'public_key'            => $this->publicKey,
            'signature'             => $this->signature,
            'valid_from'            => $this->validFrom->format('c'),
            'valid_until'           => $this->validUntil->format('c'),
            'status'                => $this->status->value,
            'parent_certificate_id' => $this->parentCertificateId,
            'extensions'            => $this->extensions,
            'revoked_at'            => $this->revokedAt?->format('c'),
            'revocation_reason'     => $this->revocationReason,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            certificateId: $data['certificate_id'],
            subjectId: $data['subject_id'],
            subject: $data['subject'],
            publicKey: $data['public_key'],
            signature: $data['signature'],
            validFrom: new DateTimeImmutable($data['valid_from']),
            validUntil: new DateTimeImmutable($data['valid_until']),
            status: CertificateStatus::from($data['status']),
            parentCertificateId: $data['parent_certificate_id'] ?? null,
            extensions: $data['extensions'] ?? [],
            revokedAt: isset($data['revoked_at']) ? new DateTimeImmutable($data['revoked_at']) : null,
            revocationReason: $data['revocation_reason'] ?? null,
        );
    }
}
