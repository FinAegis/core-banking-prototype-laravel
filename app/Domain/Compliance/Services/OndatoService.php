<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Events\KycVerificationFailed;
use App\Domain\Compliance\Events\KycVerificationStarted;
use App\Domain\Compliance\Models\KycVerification;
use App\Domain\TrustCert\Services\CertificateAuthorityService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class OndatoService
{
    private const CACHE_TOKEN_KEY = 'ondato:access_token';

    /**
     * Statuses a webhook may still move a verification out of. Completed,
     * failed and expired are final: a later webhook never moves a
     * verification back (for example from approved to pending), and a failed
     * or expired verification is never approved by a later delivery.
     */
    private const OPEN_STATUSES = [
        KycVerification::STATUS_PENDING,
        KycVerification::STATUS_IN_PROGRESS,
    ];

    /** Ondato API statuses that confirm an approval (compared upper-case). */
    private const API_APPROVED_STATUSES = ['APPROVED', 'PROCESSED', 'CROSS_CHECKED'];

    private const DOCUMENT_TYPE_MAP = [
        'Passport'        => 'passport',
        'IdCard'          => 'national_id',
        'DriverLicense'   => 'driving_license',
        'ResidencePermit' => 'residence_permit',
    ];

    private string $kycApiUrl;

    private string $verifIdApiUrl;

    private string $applicationId;

    private string $secret;

    private string $setupId;

    private bool $sandbox;

    private string $webhookSecret;

    public function __construct()
    {
        $this->applicationId = (string) config('services.ondato.application_id', '');
        $this->secret = (string) config('services.ondato.secret', '');
        $this->setupId = (string) config('services.ondato.setup_id', '');
        $this->sandbox = (bool) config('services.ondato.sandbox', true);
        $this->webhookSecret = (string) config('services.ondato.webhook_secret', '');
        $this->kycApiUrl = rtrim((string) config('services.ondato.kyc_api_url', 'https://sandbox-kycapi.ondato.com'), '/');
        $this->verifIdApiUrl = rtrim((string) config('services.ondato.verifid_api_url', 'https://verifid.ondato.com'), '/');
    }

    /**
     * Get an OAuth2 access token from Ondato VerifId API, cached until near-expiry.
     */
    public function getAccessToken(): string
    {
        $cached = Cache::get(self::CACHE_TOKEN_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = Http::asForm()->post("{$this->verifIdApiUrl}/v3/oauth/token", [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->applicationId,
            'client_secret' => $this->secret,
        ]);

        if (! $response->successful()) {
            Log::error('Ondato OAuth token request failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new RuntimeException('Failed to obtain Ondato access token');
        }

        $data = $response->json();
        $token = $data['access_token'] ?? '';
        $expiresIn = (int) ($data['expires_in'] ?? 3600);

        // Cache with 60-second buffer before actual expiry
        $ttl = max($expiresIn - 60, 60);
        Cache::put(self::CACHE_TOKEN_KEY, $token, $ttl);

        return $token;
    }

    /**
     * Create an identity verification session via the Ondato KYC API.
     *
     * @param  array<string, mixed>  $data  Optional user data (first_name, last_name, application_id, target_level)
     * @return array<string, mixed>  Contains identity_verification_id, verification_id, status
     */
    public function createIdentityVerification(User $user, array $data = []): array
    {
        $token = $this->getAccessToken();

        $payload = [
            'setupId' => $this->setupId,
        ];

        // Add external reference to tie back to our user
        if ($user->uuid) {
            $payload['externalReferenceId'] = $user->uuid;
        }

        // Add registration data if provided
        $registration = [];
        if (! empty($data['first_name'])) {
            $registration['firstName'] = $data['first_name'];
        }
        if (! empty($data['last_name'])) {
            $registration['lastName'] = $data['last_name'];
        }
        if (! empty($registration)) {
            $payload['registration'] = $registration;
        }

        $response = Http::withToken($token)
            ->post("{$this->kycApiUrl}/v1/identity-verifications", $payload);

        if (! $response->successful()) {
            Log::error('Ondato identity verification creation failed', [
                'status'  => $response->status(),
                'body'    => $response->body(),
                'user_id' => $user->id,
            ]);
            throw new RuntimeException('Failed to create Ondato identity verification session');
        }

        $responseData = $response->json();
        $idvId = $responseData['id'] ?? '';

        // Store a KycVerification record with optional TrustCert application linkage
        $verification = KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => $idvId,
            'application_id'     => $data['application_id'] ?? null,
            'target_level'       => $data['target_level'] ?? null,
            'verification_data'  => [
                'setup_id'   => $this->setupId,
                'sandbox'    => $this->sandbox,
                'created_at' => now()->toIso8601String(),
                'response'   => $responseData,
            ],
            'started_at' => now(),
        ]);

        event(new KycVerificationStarted($verification));

        return [
            'identity_verification_id' => $idvId,
            'verification_id'          => $verification->id,
            'status'                   => $verification->status,
        ];
    }

    /**
     * Get the status of an identity verification from Ondato.
     *
     * @return array<string, mixed>
     */
    public function getIdentityVerificationStatus(string $idvId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->kycApiUrl}/v1/identity-verifications/{$idvId}");

        if (! $response->successful()) {
            Log::error('Ondato identity verification status check failed', [
                'status' => $response->status(),
                'idv_id' => $idvId,
            ]);
            throw new RuntimeException('Failed to get Ondato identity verification status');
        }

        return $response->json();
    }

    /**
     * Get full identification data (document details, face match, etc.).
     *
     * @return array<string, mixed>
     */
    public function getIdentificationData(string $identificationId): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->get("{$this->kycApiUrl}/v1/identifications/{$identificationId}");

        if (! $response->successful()) {
            Log::error('Ondato identification data fetch failed', [
                'status'            => $response->status(),
                'identification_id' => $identificationId,
            ]);
            throw new RuntimeException('Failed to get Ondato identification data');
        }

        return $response->json();
    }

    /**
     * Validate the webhook signature using HMAC-SHA256.
     */
    public function validateWebhookSignature(string $payload, string $signature): bool
    {
        if ($this->webhookSecret === '') {
            // Sandbox mode without a secret accepts unsigned webhooks in local
            // and testing only. A webhook can approve KYC, so every other
            // environment (production, staging, demo) needs
            // ONDATO_WEBHOOK_SECRET.
            if ($this->sandbox && app()->environment(['local', 'testing'])) {
                return true;
            }

            Log::warning('Ondato webhook secret not configured');

            return false;
        }

        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);

        return hash_equals($expected, $signature);
    }

    /**
     * Process an incoming webhook event from Ondato.
     *
     * @param  array<string, mixed>  $payload
     */
    public function processWebhook(string $eventType, array $payload): void
    {
        Log::info('Processing Ondato webhook', [
            'event_type' => $eventType,
            'payload_id' => $payload['id'] ?? null,
        ]);

        match ($eventType) {
            'PROCESSED', 'CROSS_CHECKED' => $this->handleVerificationApproved($payload),
            'REJECTED'                   => $this->handleVerificationRejected($payload),
            'EXPIRED'                    => $this->handleVerificationExpired($payload),
            'STARTED'                    => $this->handleVerificationStarted($payload),
            'CONSENT_AGREEMENT_ACCEPTED' => $this->handleConsentAccepted($payload),
            default                      => Log::info('Unhandled Ondato webhook event', ['event_type' => $eventType]),
        };
    }

    /**
     * Handle an approved/cross-checked verification.
     *
     * The webhook only says that something changed. The result and the
     * identity fields are read from the Ondato API by the reference stored
     * when the session was created, never taken from the webhook body.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleVerificationApproved(array $payload): void
    {
        $verification = $this->findVerificationByPayload($payload);
        if (! $verification || ! $this->isOpen($verification, 'approve')) {
            return;
        }

        $record = $this->confirmedApprovalRecord($verification);
        if ($record === null) {
            return;
        }

        if (! $this->transition($verification, self::OPEN_STATUSES, KycVerification::STATUS_COMPLETED, ['completed_at' => now()], 'approve')) {
            return;
        }

        $extractedData = $this->extractDocumentData($record);

        $verification->update([
            'confidence_score'  => 95.0,
            'extracted_data'    => $extractedData,
            'verification_data' => array_merge(
                $verification->verification_data ?? [],
                ['ondato_result' => $record]
            ),
            'document_type'    => $extractedData['document_type'] ?? null,
            'document_number'  => $extractedData['document_number'] ?? null,
            'document_country' => $extractedData['issuing_country'] ?? null,
            'first_name'       => $extractedData['first_name'] ?? null,
            'last_name'        => $extractedData['last_name'] ?? null,
            'date_of_birth'    => $extractedData['date_of_birth'] ?? null,
            'nationality'      => $extractedData['nationality'] ?? null,
        ]);

        // A rejection processed meanwhile wins: no approval for the user.
        if ($verification->fresh()?->status !== KycVerification::STATUS_COMPLETED) {
            return;
        }

        // Update user KYC status
        /** @var User|null $user */
        $user = $verification->user;
        if ($user) {
            $user->update([
                'kyc_status'      => 'approved',
                'kyc_approved_at' => now(),
                'kyc_expires_at'  => now()->addYears(2),
                'kyc_level'       => 'enhanced',
            ]);
        }

        // If linked to a TrustCertApplication, update it and issue certificate
        if ($verification->application_id) {
            $this->updateTrustCertApplicationApproved($verification);
        }

        Log::info('Ondato verification approved', [
            'verification_id' => $verification->id,
            'user_id'         => $verification->user_id,
        ]);
    }

    /**
     * The Ondato API record of the verification when it confirms the
     * approval, or null (logged) when it does not.
     *
     * @return array<string, mixed>|null
     */
    private function confirmedApprovalRecord(KycVerification $verification): ?array
    {
        $idvId = (string) $verification->provider_reference;
        if ($idvId === '') {
            Log::warning('Ondato approval not applied: verification has no Ondato reference', [
                'verification_id' => $verification->id,
            ]);

            return null;
        }

        $record = $this->getIdentityVerificationStatus($idvId);
        $status = strtoupper(is_string($record['status'] ?? null) ? $record['status'] : '');

        if (! in_array($status, self::API_APPROVED_STATUSES, true)) {
            Log::warning('Ondato approval not applied: the Ondato API does not confirm it', [
                'verification_id' => $verification->id,
                'api_status'      => $status,
            ]);

            return null;
        }

        // The session was created with our user's uuid as its external
        // reference; a record that names someone else is not this user's.
        $reference = $record['externalReferenceId'] ?? null;
        $userUuid = (string) ($verification->user->uuid ?? '');
        if (is_string($reference) && $reference !== '' && $reference !== $userUuid) {
            Log::warning('Ondato approval not applied: the Ondato record names another external reference', [
                'verification_id' => $verification->id,
            ]);

            return null;
        }

        return $record;
    }

    /**
     * Handle a rejected verification.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleVerificationRejected(array $payload): void
    {
        $verification = $this->findVerificationByPayload($payload);
        if (! $verification) {
            return;
        }

        $reason = $this->extractRejectionReason($payload);

        // A rejection may follow an approval (it only takes access away), but
        // a failed or expired verification is final.
        $from = [...self::OPEN_STATUSES, KycVerification::STATUS_COMPLETED];
        if (! $this->transition($verification, $from, KycVerification::STATUS_FAILED, ['failure_reason' => $reason, 'completed_at' => now()], 'reject')) {
            return;
        }

        // Update verification_data with rejection details
        $verification->update([
            'verification_data' => array_merge(
                $verification->verification_data ?? [],
                ['ondato_rejection' => $payload]
            ),
        ]);

        // Update user KYC status
        /** @var User|null $user */
        $user = $verification->user;
        if ($user) {
            $user->update(['kyc_status' => 'rejected']);
        }

        // If linked to a TrustCertApplication, update it
        if ($verification->application_id) {
            $this->updateTrustCertApplicationRejected($verification, $reason);
        }

        event(new KycVerificationFailed($verification, $reason));

        Log::info('Ondato verification rejected', [
            'verification_id' => $verification->id,
            'reason'          => $reason,
        ]);
    }

    /**
     * Handle an expired verification.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleVerificationExpired(array $payload): void
    {
        $verification = $this->findVerificationByPayload($payload);
        if (! $verification) {
            return;
        }

        if (! $this->transition($verification, self::OPEN_STATUSES, KycVerification::STATUS_EXPIRED, [], 'expire')) {
            return;
        }

        /** @var User|null $user */
        $user = $verification->user;
        if ($user && $user->kyc_status === 'pending') {
            $user->update(['kyc_status' => 'expired']);
        }

        Log::info('Ondato verification expired', [
            'verification_id' => $verification->id,
        ]);
    }

    /**
     * Handle verification started event (informational).
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleVerificationStarted(array $payload): void
    {
        $verification = $this->findVerificationByPayload($payload);
        if (! $verification) {
            return;
        }

        // Conditional update: a STARTED processed after a final status must
        // not move the verification back.
        KycVerification::query()
            ->whereKey($verification->getKey())
            ->where('status', KycVerification::STATUS_PENDING)
            ->update(['status' => KycVerification::STATUS_IN_PROGRESS]);

        Log::info('Ondato verification started by user', [
            'verification_id' => $verification->id,
        ]);
    }

    /**
     * Handle consent agreement accepted event.
     *
     * @param  array<string, mixed>  $payload
     */
    private function handleConsentAccepted(array $payload): void
    {
        $verification = $this->findVerificationByPayload($payload);
        if (! $verification) {
            return;
        }

        $verification->update([
            'verification_data' => array_merge(
                $verification->verification_data ?? [],
                ['consent_accepted_at' => now()->toIso8601String()]
            ),
        ]);

        Log::info('Ondato consent accepted', [
            'verification_id' => $verification->id,
        ]);
    }

    /**
     * Update TrustCertApplication to approved and issue a certificate.
     */
    private function updateTrustCertApplicationApproved(KycVerification $verification): void
    {
        $application = Cache::get("trustcert_application:{$verification->user_id}");
        if (! $application || ($application['id'] ?? null) !== $verification->application_id) {
            Log::warning('TrustCert application not found for approved verification', [
                'verification_id' => $verification->id,
                'application_id'  => $verification->application_id,
            ]);

            return;
        }

        $application['status'] = 'approved';
        $application['kyc_verification_id'] = $verification->id;
        $application['approved_at'] = now()->toIso8601String();
        Cache::put("trustcert_application:{$verification->user_id}", $application, now()->addDays(30));

        // Issue a TrustCert via CertificateAuthorityService
        /** @var User|null $user */
        $user = $verification->user;
        if ($user) {
            try {
                /** @var CertificateAuthorityService $caService */
                $caService = app(CertificateAuthorityService::class);
                $caService->issueCertificate(
                    subjectId: "user:{$user->uuid}",
                    subject: [
                        'name'  => $user->name,
                        'email' => $user->email,
                        'level' => $verification->target_level,
                    ],
                    validFrom: now(),
                    validUntil: now()->addYears(2),
                    extensions: [
                        'kyc_verification_id' => $verification->id,
                        'application_id'      => $verification->application_id,
                    ],
                );

                Log::info('TrustCert issued for approved KYC verification', [
                    'verification_id' => $verification->id,
                    'application_id'  => $verification->application_id,
                    'user_uuid'       => $user->uuid,
                ]);
            } catch (Throwable $e) {
                Log::error('Failed to issue TrustCert for approved verification', [
                    'verification_id' => $verification->id,
                    'error'           => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Update TrustCertApplication to rejected with failure reason.
     */
    private function updateTrustCertApplicationRejected(KycVerification $verification, string $reason): void
    {
        $application = Cache::get("trustcert_application:{$verification->user_id}");
        if (! $application || ($application['id'] ?? null) !== $verification->application_id) {
            return;
        }

        $application['status'] = 'rejected';
        $application['failure_reason'] = $reason;
        $application['rejected_at'] = now()->toIso8601String();
        Cache::put("trustcert_application:{$verification->user_id}", $application, now()->addDays(30));
    }

    /**
     * Whether a webhook may still change the verification (logged when not).
     */
    private function isOpen(KycVerification $verification, string $action): bool
    {
        if (in_array($verification->status, self::OPEN_STATUSES, true)) {
            return true;
        }

        Log::warning('Ondato webhook ignored: the verification is already final', [
            'verification_id' => $verification->id,
            'status'          => $verification->status,
            'action'          => $action,
        ]);

        return false;
    }

    /**
     * Move the verification to $to only while its status is one of $from, in
     * one conditional update, so two webhooks processed at the same time
     * cannot both apply and a final status is never moved back.
     *
     * @param  list<string>  $from
     * @param  array<model-property<KycVerification>, mixed>  $attributes
     */
    private function transition(KycVerification $verification, array $from, string $to, array $attributes, string $action): bool
    {
        $moved = KycVerification::query()
            ->whereKey($verification->getKey())
            ->whereIn('status', $from)
            ->update(['status' => $to] + $attributes) === 1;

        if (! $moved) {
            Log::warning('Ondato webhook ignored: status change not allowed', [
                'verification_id' => $verification->id,
                'status'          => $verification->fresh()?->status,
                'to'              => $to,
                'action'          => $action,
            ]);

            return false;
        }

        $verification->refresh();

        return true;
    }

    /**
     * Find the KycVerification record from the webhook payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function findVerificationByPayload(array $payload): ?KycVerification
    {
        // Ondato webhooks contain an identityVerificationId or id field
        $idvId = $payload['identityVerificationId']
            ?? $payload['id']
            ?? null;

        if (! $idvId) {
            Log::warning('Ondato webhook missing verification reference', [
                'payload_keys' => array_keys($payload),
            ]);

            return null;
        }

        $verification = KycVerification::where('provider', 'ondato')
            ->where('provider_reference', $idvId)
            ->first();

        if (! $verification) {
            Log::warning('Ondato verification not found for webhook', [
                'idv_id' => $idvId,
            ]);
        }

        return $verification;
    }

    /**
     * Extract document and identity data from an Ondato API record.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function extractDocumentData(array $record): array
    {
        $document = is_array($record['document'] ?? null) ? $record['document'] : [];
        $person = is_array($record['person'] ?? null) ? $record['person'] : [];

        $ondatoDocType = $document['type'] ?? '';
        $documentType = self::DOCUMENT_TYPE_MAP[$ondatoDocType] ?? 'other';

        return [
            'first_name'      => $person['firstName'] ?? null,
            'last_name'       => $person['lastName'] ?? null,
            'date_of_birth'   => $person['dateOfBirth'] ?? null,
            'nationality'     => $person['nationality'] ?? null,
            'document_type'   => $documentType,
            'document_number' => $document['number'] ?? null,
            'issuing_country' => $document['issuingCountry'] ?? null,
            'expiry_date'     => $document['expiryDate'] ?? null,
        ];
    }

    /**
     * Extract a human-readable rejection reason from the payload.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractRejectionReason(array $payload): string
    {
        if (isset($payload['rejectReason'])) {
            return (string) $payload['rejectReason'];
        }

        if (isset($payload['rejectLabels']) && is_array($payload['rejectLabels'])) {
            return implode(', ', $payload['rejectLabels']);
        }

        return 'Verification rejected by Ondato';
    }
}
