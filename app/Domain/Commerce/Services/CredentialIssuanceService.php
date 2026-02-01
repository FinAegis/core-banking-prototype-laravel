<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Enums\CredentialType;
use App\Domain\Commerce\Events\CredentialIssued;
use App\Domain\Commerce\ValueObjects\VerifiableCredential;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Service for issuing W3C Verifiable Credentials.
 *
 * Issues standards-compliant verifiable credentials for:
 * - KYC verification
 * - Accreditation status
 * - Professional certifications
 * - Educational qualifications
 */
class CredentialIssuanceService
{
    private readonly string $signingKey;

    public function __construct(
        private readonly string $issuerId = 'did:finaegis:issuer',
        ?string $signingKey = null,
        private readonly string $revocationListUrl = 'https://finaegis.example/revocation',
    ) {
        $this->signingKey = $signingKey ?? hash('sha256', 'demo-credential-key');
    }

    /**
     * Issue a verifiable credential.
     *
     * @param array<string, mixed> $credentialSubject
     * @param array<string>        $additionalContext
     */
    public function issueCredential(
        CredentialType $type,
        string $subjectId,
        array $credentialSubject,
        ?int $validityDays = null,
        array $additionalContext = [],
    ): VerifiableCredential {
        $credentialId = 'urn:uuid:' . Str::uuid()->toString();
        $issuedAt = new DateTimeImmutable();

        // Calculate expiry
        $validity = $validityDays ?? $type->defaultValidityDays();
        $expiresAt = $validity > 0
            ? $issuedAt->modify("+{$validity} days")
            : null;

        // Build context
        $context = array_merge(
            ['https://www.w3.org/2018/credentials/v1'],
            $additionalContext
        );

        // Generate proof
        $proof = $this->generateProof($credentialId, $type, $subjectId, $credentialSubject, $issuedAt);

        $credential = new VerifiableCredential(
            credentialId: $credentialId,
            type: $type,
            issuerId: $this->issuerId,
            subjectId: 'did:user:' . $subjectId,
            credentialSubject: $credentialSubject,
            proof: $proof,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
            context: $context,
            credentialStatus: $this->revocationListUrl . '#' . $credentialId,
        );

        Event::dispatch(new CredentialIssued(
            credentialId: $credentialId,
            credentialType: $type,
            issuerId: $this->issuerId,
            subjectId: $subjectId,
            issuedAt: $issuedAt,
        ));

        return $credential;
    }

    /**
     * Issue a KYC verification credential.
     */
    public function issueKycCredential(
        string $userId,
        int $verificationLevel,
        string $verificationMethod,
        ?DateTimeImmutable $verifiedAt = null,
    ): VerifiableCredential {
        return $this->issueCredential(
            type: CredentialType::KYC_VERIFICATION,
            subjectId: $userId,
            credentialSubject: [
                'verificationLevel'  => $verificationLevel,
                'verificationMethod' => $verificationMethod,
                'verifiedAt'         => ($verifiedAt ?? new DateTimeImmutable())->format('c'),
            ],
        );
    }

    /**
     * Issue an accredited investor credential.
     */
    public function issueAccreditationCredential(
        string $userId,
        string $accreditationType,
        string $jurisdiction,
    ): VerifiableCredential {
        return $this->issueCredential(
            type: CredentialType::ACCREDITATION,
            subjectId: $userId,
            credentialSubject: [
                'accreditationType' => $accreditationType,
                'jurisdiction'      => $jurisdiction,
                'accreditedSince'   => (new DateTimeImmutable())->format('c'),
            ],
        );
    }

    /**
     * Issue a professional credential.
     */
    public function issueProfessionalCredential(
        string $userId,
        string $profession,
        string $licenseNumber,
        string $issuingAuthority,
        ?DateTimeImmutable $validUntil = null,
    ): VerifiableCredential {
        $validityDays = $validUntil !== null
            ? max(1, (int) ((new DateTimeImmutable())->diff($validUntil)->days))
            : 365 * 2;

        return $this->issueCredential(
            type: CredentialType::PROFESSIONAL,
            subjectId: $userId,
            credentialSubject: [
                'profession'       => $profession,
                'licenseNumber'    => $licenseNumber,
                'issuingAuthority' => $issuingAuthority,
            ],
            validityDays: $validityDays,
        );
    }

    /**
     * Issue a payment history credential.
     */
    public function issuePaymentHistoryCredential(
        string $userId,
        string $ratingCategory,
        int $score,
        int $transactionCount,
    ): VerifiableCredential {
        return $this->issueCredential(
            type: CredentialType::PAYMENT_HISTORY,
            subjectId: $userId,
            credentialSubject: [
                'ratingCategory'   => $ratingCategory,
                'score'            => $score,
                'transactionCount' => $transactionCount,
                'assessmentDate'   => (new DateTimeImmutable())->format('c'),
            ],
        );
    }

    /**
     * Verify a credential's proof.
     */
    public function verifyCredential(VerifiableCredential $credential): bool
    {
        // Check validity
        if (! $credential->isValid()) {
            return false;
        }

        // Verify issuer
        if ($credential->issuerId !== $this->issuerId) {
            return false;
        }

        // Extract subject ID without prefix
        $subjectId = str_replace('did:user:', '', $credential->subjectId);

        // Verify proof
        $expectedProof = $this->generateProof(
            $credential->credentialId,
            $credential->type,
            $subjectId,
            $credential->credentialSubject,
            $credential->issuedAt,
        );

        return hash_equals($expectedProof, $credential->proof);
    }

    /**
     * Generate a verifiable presentation from credentials.
     *
     * @param array<VerifiableCredential> $credentials
     *
     * @return array<string, mixed>
     */
    public function createPresentation(
        array $credentials,
        string $holderId,
        ?string $challenge = null,
    ): array {
        $presentationId = 'urn:uuid:' . Str::uuid()->toString();

        $presentation = [
            '@context'             => ['https://www.w3.org/2018/credentials/v1'],
            'type'                 => ['VerifiablePresentation'],
            'id'                   => $presentationId,
            'holder'               => 'did:user:' . $holderId,
            'verifiableCredential' => array_map(
                fn (VerifiableCredential $vc) => $vc->toW3CFormat(),
                $credentials
            ),
            'proof' => [
                'type'               => 'Ed25519Signature2020',
                'created'            => (new DateTimeImmutable())->format('c'),
                'verificationMethod' => 'did:user:' . $holderId . '#key-1',
                'proofPurpose'       => 'authentication',
                'challenge'          => $challenge ?? Str::random(32),
                'proofValue'         => $this->generatePresentationProof(
                    $presentationId,
                    $credentials,
                    $holderId
                ),
            ],
        ];

        return $presentation;
    }

    /**
     * Generate proof for a credential.
     *
     * @param array<string, mixed> $credentialSubject
     */
    private function generateProof(
        string $credentialId,
        CredentialType $type,
        string $subjectId,
        array $credentialSubject,
        DateTimeInterface $issuedAt,
    ): string {
        $data = [
            'credential_id' => $credentialId,
            'type'          => $type->value,
            'issuer_id'     => $this->issuerId,
            'subject_id'    => $subjectId,
            'subject_hash'  => hash('sha256', json_encode($credentialSubject, JSON_THROW_ON_ERROR)),
            'issued_at'     => $issuedAt->format('c'),
        ];

        return base64_encode(hash_hmac(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR),
            $this->signingKey,
            true
        ));
    }

    /**
     * Generate proof for a presentation.
     *
     * @param array<VerifiableCredential> $credentials
     */
    private function generatePresentationProof(
        string $presentationId,
        array $credentials,
        string $holderId,
    ): string {
        $data = [
            'presentation_id'   => $presentationId,
            'holder_id'         => $holderId,
            'credential_hashes' => array_map(
                fn (VerifiableCredential $vc) => $vc->getCredentialHash(),
                $credentials
            ),
        ];

        return base64_encode(hash_hmac(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR),
            $this->signingKey,
            true
        ));
    }
}
