<?php

declare(strict_types=1);

namespace App\Domain\TrustCert\Services;

use App\Domain\TrustCert\Contracts\CertificateAuthorityInterface;
use App\Domain\TrustCert\Enums\CertificateStatus;
use App\Domain\TrustCert\Events\Broadcast\TrustCertStatusChanged;
use App\Domain\TrustCert\Events\CertificateIssued;
use App\Domain\TrustCert\Events\CertificateRevoked;
use App\Domain\TrustCert\ValueObjects\Certificate;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Internal Certificate Authority for credential signing.
 */
class CertificateAuthorityService implements CertificateAuthorityInterface
{
    private const CACHE_PREFIX = 'ca_cert:';

    private const CACHE_SUBJECT_PREFIX = 'ca_subject:';

    /** @var array<string, Certificate> */
    private array $certificates = [];

    /** @var array<string, string> */
    private array $subjectIndex = [];

    public function __construct(
        private readonly string $caId = 'finaegis-root-ca',
        private readonly string $signingKey = '',
    ) {
        if (app()->environment('production') && empty($this->signingKey)) {
            throw new RuntimeException('CA signing key must be configured in production');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function issueCertificate(
        string $subjectId,
        array $subject,
        DateTimeInterface $validFrom,
        DateTimeInterface $validUntil,
        ?string $parentCertificateId = null,
        array $extensions = [],
    ): Certificate {
        // Validate parent certificate if specified
        if ($parentCertificateId !== null) {
            $parent = $this->getCertificate($parentCertificateId);
            if ($parent === null || ! $parent->canSign()) {
                throw new InvalidArgumentException('Parent certificate is invalid or cannot sign');
            }
        }

        // Generate certificate ID
        $certificateId = 'cert_' . Str::uuid()->toString();

        // Generate key pair (in production, use proper key generation)
        $publicKey = $this->generatePublicKey($subjectId, $certificateId);

        // Generate signature
        $signature = $this->signCertificate($certificateId, $subjectId, $publicKey, $validFrom, $validUntil);

        $certificate = new Certificate(
            certificateId: $certificateId,
            subjectId: $subjectId,
            subject: $subject,
            publicKey: $publicKey,
            signature: $signature,
            validFrom: $validFrom,
            validUntil: $validUntil,
            status: CertificateStatus::ACTIVE,
            parentCertificateId: $parentCertificateId,
            extensions: $extensions,
        );

        // Persist in both memory and cache
        $this->certificates[$certificateId] = $certificate;
        $this->subjectIndex[$subjectId] = $certificateId;
        Cache::put(self::CACHE_PREFIX . $certificateId, $certificate);
        Cache::put(self::CACHE_SUBJECT_PREFIX . $subjectId, $certificateId);

        // Dispatch event
        Event::dispatch(new CertificateIssued(
            certificateId: $certificateId,
            subjectId: $subjectId,
            validFrom: $validFrom,
            validUntil: $validUntil,
            parentCertificateId: $parentCertificateId,
            issuedAt: new DateTimeImmutable(),
        ));

        // Broadcast certificate status change for mobile
        TrustCertStatusChanged::dispatch(
            userId: $subjectId,
            certificateId: $certificateId,
            status: CertificateStatus::ACTIVE->value,
            subjectId: $subjectId,
            changedAt: (new DateTimeImmutable())->format('c'),
        );

        return $certificate;
    }

    /**
     * {@inheritDoc}
     */
    public function revokeCertificate(string $certificateId, string $reason): bool
    {
        $certificate = $this->getCertificate($certificateId);
        if ($certificate === null) {
            return false;
        }

        if ($certificate->status->isTerminal()) {
            return false;
        }

        $revokedAt = new DateTimeImmutable();

        // Create revoked certificate
        $revokedCertificate = new Certificate(
            certificateId: $certificate->certificateId,
            subjectId: $certificate->subjectId,
            subject: $certificate->subject,
            publicKey: $certificate->publicKey,
            signature: $certificate->signature,
            validFrom: $certificate->validFrom,
            validUntil: $certificate->validUntil,
            status: CertificateStatus::REVOKED,
            parentCertificateId: $certificate->parentCertificateId,
            extensions: $certificate->extensions,
            revokedAt: $revokedAt,
            revocationReason: $reason,
        );

        // Persist in both memory and cache; remove from subject index
        $this->certificates[$certificateId] = $revokedCertificate;
        Cache::put(self::CACHE_PREFIX . $certificateId, $revokedCertificate);
        unset($this->subjectIndex[$certificate->subjectId]);
        Cache::forget(self::CACHE_SUBJECT_PREFIX . $certificate->subjectId);

        Event::dispatch(new CertificateRevoked(
            certificateId: $certificateId,
            reason: $reason,
            revokedAt: $revokedAt,
        ));

        // Broadcast certificate status change for mobile
        TrustCertStatusChanged::dispatch(
            userId: $certificate->subjectId,
            certificateId: $certificateId,
            status: CertificateStatus::REVOKED->value,
            subjectId: $certificate->subjectId,
            changedAt: $revokedAt->format('c'),
        );

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function suspendCertificate(string $certificateId, string $reason): bool
    {
        $certificate = $this->getCertificate($certificateId);
        if ($certificate === null) {
            return false;
        }

        if (! $certificate->status->canTransitionTo(CertificateStatus::SUSPENDED)) {
            return false;
        }

        $suspendedCertificate = new Certificate(
            certificateId: $certificate->certificateId,
            subjectId: $certificate->subjectId,
            subject: $certificate->subject,
            publicKey: $certificate->publicKey,
            signature: $certificate->signature,
            validFrom: $certificate->validFrom,
            validUntil: $certificate->validUntil,
            status: CertificateStatus::SUSPENDED,
            parentCertificateId: $certificate->parentCertificateId,
            extensions: array_merge($certificate->extensions, ['suspension_reason' => $reason]),
        );

        // Persist in both memory and cache
        $this->certificates[$certificateId] = $suspendedCertificate;
        Cache::put(self::CACHE_PREFIX . $certificateId, $suspendedCertificate);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function reinstateCertificate(string $certificateId): bool
    {
        $certificate = $this->getCertificate($certificateId);
        if ($certificate === null) {
            return false;
        }

        if (! $certificate->isSuspended()) {
            return false;
        }

        // Check if certificate is still within validity period
        if ($certificate->isExpired()) {
            return false;
        }

        $extensions = $certificate->extensions;
        unset($extensions['suspension_reason']);

        $reinstatedCertificate = new Certificate(
            certificateId: $certificate->certificateId,
            subjectId: $certificate->subjectId,
            subject: $certificate->subject,
            publicKey: $certificate->publicKey,
            signature: $certificate->signature,
            validFrom: $certificate->validFrom,
            validUntil: $certificate->validUntil,
            status: CertificateStatus::ACTIVE,
            parentCertificateId: $certificate->parentCertificateId,
            extensions: $extensions,
        );

        // Persist in both memory and cache; restore subject index
        $this->certificates[$certificateId] = $reinstatedCertificate;
        $this->subjectIndex[$certificate->subjectId] = $certificateId;
        Cache::put(self::CACHE_PREFIX . $certificateId, $reinstatedCertificate);
        Cache::put(self::CACHE_SUBJECT_PREFIX . $certificate->subjectId, $certificateId);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getCertificate(string $certificateId): ?Certificate
    {
        return $this->certificates[$certificateId]
            ?? Cache::get(self::CACHE_PREFIX . $certificateId);
    }

    /**
     * {@inheritDoc}
     */
    public function verifyCertificate(string $certificateId): bool
    {
        $certificate = $this->getCertificate($certificateId);
        if ($certificate === null) {
            return false;
        }

        // Check basic validity
        if (! $certificate->isValid()) {
            return false;
        }

        // Verify signature
        $expectedSignature = $this->signCertificate(
            $certificate->certificateId,
            $certificate->subjectId,
            $certificate->publicKey,
            $certificate->validFrom,
            $certificate->validUntil,
        );

        if ($certificate->signature !== $expectedSignature) {
            return false;
        }

        // Verify parent chain
        if ($certificate->parentCertificateId !== null) {
            return $this->verifyCertificate($certificate->parentCertificateId);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function getCertificateStatus(string $certificateId): ?CertificateStatus
    {
        $certificate = $this->getCertificate($certificateId);
        if ($certificate === null) {
            return null;
        }

        // Check for expiration
        if ($certificate->isExpired() && $certificate->status === CertificateStatus::ACTIVE) {
            return CertificateStatus::EXPIRED;
        }

        return $certificate->status;
    }

    /**
     * Get certificate by subject ID.
     */
    public function getCertificateBySubject(string $subjectId): ?Certificate
    {
        $certificateId = $this->subjectIndex[$subjectId]
            ?? Cache::get(self::CACHE_SUBJECT_PREFIX . $subjectId);

        if ($certificateId === null) {
            return null;
        }

        return $this->getCertificate($certificateId);
    }

    /**
     * Get all active certificates.
     *
     * @return array<Certificate>
     */
    public function getActiveCertificates(): array
    {
        return array_filter(
            $this->certificates,
            fn (Certificate $cert) => $cert->isValid(),
        );
    }

    /**
     * Get CA identifier.
     */
    public function getCaId(): string
    {
        return $this->caId;
    }

    private function generatePublicKey(string $subjectId, string $certificateId): string
    {
        // In production, generate actual key pair
        $data = $subjectId . ':' . $certificateId . ':' . time();

        return base64_encode(hash('sha256', $data, true));
    }

    private function signCertificate(
        string $certificateId,
        string $subjectId,
        string $publicKey,
        DateTimeInterface $validFrom,
        DateTimeInterface $validUntil,
    ): string {
        $signingKey = $this->signingKey ?: config('trustcert.ca_signing_key', 'default-signing-key');

        $data = implode('|', [
            $certificateId,
            $subjectId,
            $publicKey,
            $validFrom->format('c'),
            $validUntil->format('c'),
            $this->caId,
        ]);

        return base64_encode(hash_hmac('sha256', $data, $signingKey, true));
    }
}
