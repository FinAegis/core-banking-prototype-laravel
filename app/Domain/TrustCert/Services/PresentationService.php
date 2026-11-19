<?php

declare(strict_types=1);

namespace App\Domain\TrustCert\Services;

use App\Domain\TrustCert\Enums\CertificateStatus;
use App\Domain\TrustCert\ValueObjects\Certificate;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Service for generating verifiable presentations of TrustCert credentials.
 *
 * This enables mobile apps to generate QR codes and deep links that can be
 * scanned to verify a user's certification status without revealing PII.
 */
class PresentationService
{
    /**
     * Default presentation validity in minutes.
     */
    private const DEFAULT_VALIDITY_MINUTES = 15;

    // TODO: Inject VerifiableCredentialService when full W3C VC integration is needed
    public function __construct(
        private readonly CertificateAuthorityService $certificateAuthority,
    ) {
    }

    /**
     * The certificate with this id, only when the given user holds it
     * (Certificate::isHeldBy). Unknown ids and other holders' certificates
     * both return null.
     */
    public function findCertificateHeldBy(string $certificateId, ?Authenticatable $user): ?Certificate
    {
        $certificate = $this->getCertificate($certificateId);

        return $certificate !== null && $certificate->isHeldBy($user) ? $certificate : null;
    }

    /**
     * Generate a verifiable presentation for a certificate.
     *
     * This creates a time-limited token that can be used to verify the certificate
     * without exposing the underlying PII.
     *
     * @param array<string> $requestedClaims
     * @return array{
     *     presentation_token: string,
     *     qr_code_data: string,
     *     deep_link: string,
     *     verification_url: string,
     *     expires_at: string,
     *     claims: array<string, mixed>
     * }
     */
    public function generatePresentation(
        string $certificateId,
        array $requestedClaims = [],
        ?int $validityMinutes = null
    ): array {
        $validityMinutes ??= self::DEFAULT_VALIDITY_MINUTES;

        Log::info('Generating verifiable presentation', [
            'certificate_id'   => $certificateId,
            'requested_claims' => $requestedClaims,
            'validity_minutes' => $validityMinutes,
        ]);

        // 1. Retrieve and validate certificate
        $certificate = $this->getCertificate($certificateId);
        if ($certificate === null) {
            throw new RuntimeException('Certificate not found');
        }

        if (! $certificate->isValid()) {
            throw new RuntimeException('Certificate is not valid: ' . $certificate->status->value);
        }

        // 2. Generate presentation token
        $presentationToken = $this->generateToken();
        $expiresAt = (new DateTimeImmutable())->modify("+{$validityMinutes} minutes");

        // 3. Build selective disclosure claims
        $claims = $this->buildClaims($certificate, $requestedClaims);

        // 4. Create verifiable presentation
        $presentation = [
            '@context' => [
                'https://www.w3.org/2018/credentials/v1',
                'https://finaegis.org/contexts/trustcert/v1',
            ],
            'type'                 => ['VerifiablePresentation', 'TrustCertPresentation'],
            'verifiableCredential' => [
                $this->createCredentialProof($certificate, $claims),
            ],
            'proof' => [
                'type'         => 'Ed25519Signature2020',
                'created'      => (new DateTimeImmutable())->format('c'),
                'proofPurpose' => 'authentication',
                'challenge'    => $presentationToken,
            ],
        ];

        // 5. Store presentation for verification
        $this->storePresentation($presentationToken, [
            'certificate_id' => $certificateId,
            'claims'         => $claims,
            'presentation'   => $presentation,
            'expires_at'     => $expiresAt->format('c'),
        ]);

        // 6. Build response URLs
        $baseUrl = config('app.url');
        $verificationUrl = "{$baseUrl}/api/v1/trustcert/verify/{$presentationToken}";
        $deepLink = "finaegis://trustcert/verify/{$presentationToken}";
        $qrCodeData = (string) json_encode([
            'type'  => 'trustcert_presentation',
            'token' => $presentationToken,
            'url'   => $verificationUrl,
        ], JSON_THROW_ON_ERROR);

        Log::info('Verifiable presentation generated', [
            'certificate_id'     => $certificateId,
            'presentation_token' => $presentationToken,
            'expires_at'         => $expiresAt->format('c'),
        ]);

        return [
            'presentation_token' => $presentationToken,
            'qr_code_data'       => $qrCodeData,
            'deep_link'          => $deepLink,
            'verification_url'   => $verificationUrl,
            'expires_at'         => $expiresAt->format('c'),
            'claims'             => $claims,
        ];
    }

    /**
     * Verify a presentation token.
     *
     * @return array{
     *     valid: bool,
     *     certificate_type: ?string,
     *     trust_level: ?string,
     *     claims: array<string, mixed>,
     *     issuer: ?string,
     *     expires_at: ?string,
     *     error: ?string
     * }
     */
    public function verifyPresentation(string $presentationToken): array
    {
        Log::info('Verifying presentation', ['token' => $presentationToken]);

        // 1. Retrieve stored presentation
        $stored = $this->getStoredPresentation($presentationToken);
        if ($stored === null) {
            return $this->invalidResponse('Presentation not found or expired');
        }

        // 2. Check expiration
        $expiresAt = new DateTimeImmutable($stored['expires_at']);
        if ($expiresAt < new DateTimeImmutable()) {
            $this->deletePresentation($presentationToken);

            return $this->invalidResponse('Presentation has expired');
        }

        // 3. Verify certificate is still valid
        $certificate = $this->getCertificate($stored['certificate_id']);
        if ($certificate === null) {
            return $this->invalidResponse('Certificate no longer exists');
        }

        if ($certificate->status !== CertificateStatus::ACTIVE) {
            return $this->invalidResponse('Certificate has been revoked or suspended');
        }

        Log::info('Presentation verified successfully', [
            'token'          => $presentationToken,
            'certificate_id' => $stored['certificate_id'],
        ]);

        $certificateType = $certificate->extensions['type'] ?? 'TRUST_CERT';
        $trustLevel = $certificate->extensions['trust_level'] ?? 'unknown';

        return [
            'valid'            => true,
            'certificate_type' => (string) $certificateType,
            'trust_level'      => (string) $trustLevel,
            'claims'           => $stored['claims'],
            'issuer'           => 'did:web:finaegis.org',
            'expires_at'       => $certificate->validUntil->format('c'),
            'error'            => null,
        ];
    }

    /**
     * Build selective disclosure claims from certificate.
     *
     * @param array<string> $requestedClaims
     * @return array<string, mixed>
     */
    private function buildClaims(Certificate $certificate, array $requestedClaims): array
    {
        $certificateType = $certificate->extensions['type'] ?? 'TRUST_CERT';

        $availableClaims = [
            'certificate_id'   => $certificate->certificateId,
            'certificate_type' => $certificateType,
            'subject_id'       => $certificate->subjectId,
            'valid_from'       => $certificate->validFrom->format('Y-m-d'),
            'valid_until'      => $certificate->validUntil->format('Y-m-d'),
            'status'           => $certificate->status->value,
            'is_root'          => $certificate->isRootCertificate(),
        ];

        // If specific claims requested, filter
        if (! empty($requestedClaims)) {
            return array_intersect_key($availableClaims, array_flip($requestedClaims));
        }

        return $availableClaims;
    }

    /**
     * Create a credential proof for the presentation.
     *
     * @param array<string, mixed> $claims
     * @return array<string, mixed>
     */
    private function createCredentialProof(Certificate $certificate, array $claims): array
    {
        $claimsJson = json_encode($claims, JSON_THROW_ON_ERROR);

        return [
            '@context'          => 'https://www.w3.org/2018/credentials/v1',
            'type'              => ['VerifiableCredential', 'TrustCertCredential'],
            'issuer'            => 'did:web:finaegis.org',
            'issuanceDate'      => $certificate->validFrom->format('c'),
            'expirationDate'    => $certificate->validUntil->format('c'),
            'credentialSubject' => $claims,
            'proof'             => [
                'type'               => 'Ed25519Signature2020',
                'created'            => (new DateTimeImmutable())->format('c'),
                'verificationMethod' => 'did:web:finaegis.org#key-1',
                'proofValue'         => base64_encode(hash('sha256', $claimsJson, true)),
            ],
        ];
    }

    /**
     * Generate a secure presentation token.
     */
    private function generateToken(): string
    {
        return Str::random(64);
    }

    /**
     * Store presentation for later verification.
     *
     * @param array<string, mixed> $data
     */
    private function storePresentation(string $token, array $data): void
    {
        Cache::put(
            "trustcert_presentation:{$token}",
            $data,
            now()->addHours(1)
        );
    }

    /**
     * Get stored presentation data.
     *
     * @return array<string, mixed>|null
     */
    private function getStoredPresentation(string $token): ?array
    {
        /** @var array<string, mixed>|null */
        return Cache::get("trustcert_presentation:{$token}");
    }

    /**
     * Delete a presentation.
     */
    private function deletePresentation(string $token): void
    {
        Cache::forget("trustcert_presentation:{$token}");
    }

    /**
     * Get a certificate by ID from the certificate authority (null when it
     * was never issued), so presentations carry the real claims and status
     * and verification reflects revocation and suspension.
     */
    private function getCertificate(string $certificateId): ?Certificate
    {
        return $this->certificateAuthority->getCertificate($certificateId);
    }

    /**
     * Build invalid response.
     *
     * @return array{
     *     valid: bool,
     *     certificate_type: null,
     *     trust_level: null,
     *     claims: array<never>,
     *     issuer: null,
     *     expires_at: null,
     *     error: string
     * }
     */
    private function invalidResponse(string $error): array
    {
        return [
            'valid'            => false,
            'certificate_type' => null,
            'trust_level'      => null,
            'claims'           => [],
            'issuer'           => null,
            'expires_at'       => null,
            'error'            => $error,
        ];
    }
}
