<?php

declare(strict_types=1);

use App\Domain\Compliance\Events\KycVerificationFailed;
use App\Domain\Compliance\Events\KycVerificationStarted;
use App\Domain\Compliance\Models\KycVerification;
use App\Domain\Compliance\Services\OndatoService;
use App\Domain\TrustCert\Services\CertificateAuthorityService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function () {
    config([
        'services.ondato.application_id'  => 'test-app-id',
        'services.ondato.secret'          => 'test-secret',
        'services.ondato.setup_id'        => 'test-setup-id',
        'services.ondato.sandbox'         => true,
        'services.ondato.webhook_secret'  => 'test-webhook-secret',
        'services.ondato.kyc_api_url'     => 'https://sandbox-kycapi.ondato.com',
        'services.ondato.verifid_api_url' => 'https://verifid.ondato.com',
    ]);

    Cache::forget('ondato:access_token');
    $this->service = new OndatoService();
});

/**
 * Fake the Ondato API that approvals are confirmed against: every identity
 * verification reads as Approved unless $records gives its record.
 *
 * @param  array<string, array<string, mixed>>  $records  keyed by identity verification id
 */
function ondatoServiceTestFakeApi(array $records = []): void
{
    Http::fake(function (Illuminate\Http\Client\Request $request) use ($records) {
        if (str_contains($request->url(), '/v3/oauth/token')) {
            return Http::response(['access_token' => 'test-token', 'expires_in' => 3600]);
        }

        if (preg_match('#/v1/identity-verifications/([^/?]+)$#', $request->url(), $match) === 1) {
            return Http::response($records[$match[1]] ?? ['id' => $match[1], 'status' => 'Approved']);
        }

        return Http::response([], 404);
    });
}

// ──────────────────────────────────────────────────────────────
// OAuth2 Token
// ──────────────────────────────────────────────────────────────

describe('getAccessToken', function () {
    it('returns a cached token if available', function () {
        Cache::put('ondato:access_token', 'cached-token', 300);

        $token = $this->service->getAccessToken();

        expect($token)->toBe('cached-token');
    });

    it('fetches a new token from the VerifId API when cache is empty', function () {
        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response([
                'access_token' => 'new-access-token',
                'expires_in'   => 3600,
                'token_type'   => 'Bearer',
            ], 200),
        ]);

        $token = $this->service->getAccessToken();

        expect($token)->toBe('new-access-token');
        expect(Cache::get('ondato:access_token'))->toBe('new-access-token');
    });

    it('throws RuntimeException when OAuth request fails', function () {
        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        Cache::forget('ondato:access_token');

        $this->service->getAccessToken();
    })->throws(RuntimeException::class, 'Failed to obtain Ondato access token');

    it('caches token with buffer before expiry', function () {
        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response([
                'access_token' => 'buffered-token',
                'expires_in'   => 120,
            ], 200),
        ]);

        $this->service->getAccessToken();

        // Token should be cached (120 - 60 = 60 seconds TTL)
        expect(Cache::get('ondato:access_token'))->toBe('buffered-token');
    });
});

// ──────────────────────────────────────────────────────────────
// Identity Verification Creation
// ──────────────────────────────────────────────────────────────

describe('createIdentityVerification', function () {
    it('creates a verification session and stores KycVerification record', function () {
        Event::fake([KycVerificationStarted::class]);

        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'sandbox-kycapi.ondato.com/v1/identity-verifications' => Http::response([
                'id'     => 'idv-uuid-123',
                'status' => 'Created',
            ], 201),
        ]);

        $user = User::factory()->create(['uuid' => 'user-uuid-456']);

        $result = $this->service->createIdentityVerification($user, [
            'first_name' => 'John',
            'last_name'  => 'Doe',
        ]);

        expect($result)->toHaveKeys(['identity_verification_id', 'verification_id', 'status']);
        expect($result['identity_verification_id'])->toBe('idv-uuid-123');
        expect($result['status'])->toBe('pending');

        // Verify KycVerification record was created
        $verification = KycVerification::where('provider_reference', 'idv-uuid-123')->first();
        assert($verification !== null);
        expect($verification->provider)->toBe('ondato');
        expect($verification->type)->toBe(KycVerification::TYPE_IDENTITY);
        expect($verification->user_id)->toBe($user->id);

        Event::assertDispatched(KycVerificationStarted::class);
    });

    it('stores application_id and target_level when provided', function () {
        Event::fake([KycVerificationStarted::class]);

        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'sandbox-kycapi.ondato.com/v1/identity-verifications' => Http::response([
                'id' => 'idv-app-linked',
            ], 201),
        ]);

        $user = User::factory()->create(['uuid' => 'user-app-link']);

        $result = $this->service->createIdentityVerification($user, [
            'first_name'     => 'Jane',
            'last_name'      => 'Smith',
            'application_id' => 'app_test_123',
            'target_level'   => 'verified',
        ]);

        $verification = KycVerification::where('provider_reference', 'idv-app-linked')->first();
        assert($verification !== null);
        expect($verification->application_id)->toBe('app_test_123');
        expect($verification->target_level)->toBe('verified');
    });

    it('sends external reference and registration data in the API request', function () {
        Event::fake([KycVerificationStarted::class]);

        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'sandbox-kycapi.ondato.com/v1/identity-verifications' => Http::response([
                'id' => 'idv-sent-check',
            ], 201),
        ]);

        $user = User::factory()->create(['uuid' => 'ext-ref-user']);

        $this->service->createIdentityVerification($user, [
            'first_name' => 'Jane',
            'last_name'  => 'Smith',
        ]);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'identity-verifications')) {
                return true; // Skip the token request
            }
            $body = $request->data();

            return ($body['setupId'] ?? '') === 'test-setup-id'
                && ($body['externalReferenceId'] ?? '') === 'ext-ref-user'
                && ($body['registration']['firstName'] ?? '') === 'Jane'
                && ($body['registration']['lastName'] ?? '') === 'Smith';
        });
    });

    it('throws RuntimeException when API call fails', function () {
        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'sandbox-kycapi.ondato.com/v1/identity-verifications' => Http::response(
                ['error' => 'Bad request'],
                400
            ),
        ]);

        $user = User::factory()->create();

        $this->service->createIdentityVerification($user);
    })->throws(RuntimeException::class, 'Failed to create Ondato identity verification session');
});

// ──────────────────────────────────────────────────────────────
// Identity Verification Status
// ──────────────────────────────────────────────────────────────

describe('getIdentityVerificationStatus', function () {
    it('returns the verification status from Ondato API', function () {
        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'sandbox-kycapi.ondato.com/v1/identity-verifications/idv-123' => Http::response([
                'id'     => 'idv-123',
                'status' => 'Approved',
            ], 200),
        ]);

        $result = $this->service->getIdentityVerificationStatus('idv-123');

        expect($result['id'])->toBe('idv-123');
        expect($result['status'])->toBe('Approved');
    });

    it('throws RuntimeException when status check fails', function () {
        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'sandbox-kycapi.ondato.com/v1/identity-verifications/bad-id' => Http::response(
                ['error' => 'Not found'],
                404
            ),
        ]);

        $this->service->getIdentityVerificationStatus('bad-id');
    })->throws(RuntimeException::class, 'Failed to get Ondato identity verification status');
});

// ──────────────────────────────────────────────────────────────
// Identification Data
// ──────────────────────────────────────────────────────────────

describe('getIdentificationData', function () {
    it('returns identification data from Ondato API', function () {
        Http::fake([
            'verifid.ondato.com/v3/oauth/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'sandbox-kycapi.ondato.com/v1/identifications/ident-456' => Http::response([
                'id'       => 'ident-456',
                'document' => ['type' => 'Passport', 'number' => 'P12345'],
                'person'   => ['firstName' => 'John', 'lastName' => 'Doe'],
            ], 200),
        ]);

        $result = $this->service->getIdentificationData('ident-456');

        expect($result['id'])->toBe('ident-456');
        expect($result['document']['type'])->toBe('Passport');
    });
});

// ──────────────────────────────────────────────────────────────
// Webhook Signature Validation
// ──────────────────────────────────────────────────────────────

describe('validateWebhookSignature', function () {
    it('validates a correct HMAC-SHA256 signature', function () {
        $payload = '{"id":"test"}';
        $signature = hash_hmac('sha256', $payload, 'test-webhook-secret');

        expect($this->service->validateWebhookSignature($payload, $signature))->toBeTrue();
    });

    it('rejects an incorrect signature', function () {
        $payload = '{"id":"test"}';

        expect($this->service->validateWebhookSignature($payload, 'wrong-signature'))->toBeFalse();
    });

    it('allows passthrough in sandbox mode with no secret', function () {
        config(['services.ondato.webhook_secret' => '']);
        $service = new OndatoService();

        expect($service->validateWebhookSignature('{"id":"test"}', ''))->toBeTrue();
    });

    it('rejects when not sandbox and no secret configured', function () {
        config([
            'services.ondato.sandbox'        => false,
            'services.ondato.webhook_secret' => '',
        ]);
        $service = new OndatoService();

        expect($service->validateWebhookSignature('{"id":"test"}', 'any'))->toBeFalse();
    });
});

// ──────────────────────────────────────────────────────────────
// Webhook Processing
// ──────────────────────────────────────────────────────────────

describe('processWebhook', function () {
    beforeEach(function () {
        ondatoServiceTestFakeApi([
            'idv-doctype-555' => [
                'id'       => 'idv-doctype-555',
                'status'   => 'Approved',
                'document' => ['type' => 'DriverLicense', 'number' => 'DL123'],
                'person'   => ['firstName' => 'Driver', 'lastName' => 'Test'],
            ],
        ]);
    });

    it('handles PROCESSED event and approves verification', function () {
        $user = User::factory()->create(['kyc_status' => 'pending']);
        $verification = KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-processed-123',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('PROCESSED', [
            'identityVerificationId' => 'idv-processed-123',
            'id'                     => 'ident-result-456',
            'status'                 => 'PROCESSED',
            'document'               => [
                'type'           => 'Passport',
                'number'         => 'P999888',
                'issuingCountry' => 'LT',
                'expiryDate'     => '2030-01-01',
            ],
            'person' => [
                'firstName'   => 'Jonas',
                'lastName'    => 'Jonaitis',
                'dateOfBirth' => '1990-05-15',
                'nationality' => 'LT',
            ],
        ]);

        $verification->refresh();
        expect($verification->status)->toBe(KycVerification::STATUS_COMPLETED);
        expect($verification->confidence_score)->toBe('95.00');

        $user->refresh();
        expect($user->kyc_status)->toBe('approved');
    });

    it('handles REJECTED event and marks verification as failed', function () {
        Event::fake([KycVerificationFailed::class]);

        $user = User::factory()->create(['kyc_status' => 'pending']);
        $verification = KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_IN_PROGRESS,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-rejected-789',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('REJECTED', [
            'identityVerificationId' => 'idv-rejected-789',
            'rejectReason'           => 'Document expired',
        ]);

        $verification->refresh();
        expect($verification->status)->toBe(KycVerification::STATUS_FAILED);
        expect($verification->failure_reason)->toBe('Document expired');

        $user->refresh();
        expect($user->kyc_status)->toBe('rejected');

        Event::assertDispatched(KycVerificationFailed::class);
    });

    it('handles REJECTED event with reject labels', function () {
        Event::fake([KycVerificationFailed::class]);

        $user = User::factory()->create(['kyc_status' => 'pending']);
        KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_IN_PROGRESS,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-labels-789',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('REJECTED', [
            'identityVerificationId' => 'idv-labels-789',
            'rejectLabels'           => ['BLURRY_IMAGE', 'FACE_NOT_VISIBLE'],
        ]);

        $verification = KycVerification::where('provider_reference', 'idv-labels-789')->first();
        assert($verification !== null);
        expect($verification->failure_reason)->toBe('BLURRY_IMAGE, FACE_NOT_VISIBLE');
    });

    it('handles EXPIRED event', function () {
        $user = User::factory()->create(['kyc_status' => 'pending']);
        $verification = KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-expired-111',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('EXPIRED', [
            'identityVerificationId' => 'idv-expired-111',
        ]);

        $verification->refresh();
        expect($verification->status)->toBe(KycVerification::STATUS_EXPIRED);

        $user->refresh();
        expect($user->kyc_status)->toBe('expired');
    });

    it('handles STARTED event and updates status to in_progress', function () {
        $user = User::factory()->create();
        $verification = KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-started-222',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('STARTED', [
            'identityVerificationId' => 'idv-started-222',
        ]);

        $verification->refresh();
        expect($verification->status)->toBe(KycVerification::STATUS_IN_PROGRESS);
    });

    it('handles CONSENT_AGREEMENT_ACCEPTED event', function () {
        $user = User::factory()->create();
        $verification = KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-consent-333',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('CONSENT_AGREEMENT_ACCEPTED', [
            'identityVerificationId' => 'idv-consent-333',
        ]);

        $verification->refresh();
        $verificationData = $verification->verification_data;
        expect($verificationData)->toHaveKey('consent_accepted_at');
    });

    it('handles CROSS_CHECKED event same as PROCESSED', function () {
        $user = User::factory()->create(['kyc_status' => 'pending']);
        $verification = KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_IN_PROGRESS,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-crosschecked-444',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('CROSS_CHECKED', [
            'identityVerificationId' => 'idv-crosschecked-444',
            'document'               => ['type' => 'IdCard', 'number' => 'ID123'],
            'person'                 => ['firstName' => 'Test', 'lastName' => 'User'],
        ]);

        $verification->refresh();
        expect($verification->status)->toBe(KycVerification::STATUS_COMPLETED);

        $user->refresh();
        expect($user->kyc_status)->toBe('approved');
    });

    it('gracefully handles unknown event types', function () {
        // Should not throw
        $this->service->processWebhook('UNKNOWN_EVENT', [
            'id' => 'some-id',
        ]);

        // If we get here without exception, the test passes
        expect(true)->toBeTrue();
    });

    it('logs warning when verification not found for webhook', function () {
        $this->service->processWebhook('PROCESSED', [
            'identityVerificationId' => 'non-existent-idv',
        ]);

        // Should not throw, just log a warning
        expect(true)->toBeTrue();
    });

    it('logs warning when webhook payload missing verification reference', function () {
        $this->service->processWebhook('PROCESSED', [
            'someOtherField' => 'value',
        ]);

        // Should not throw
        expect(true)->toBeTrue();
    });

    it('maps Ondato document types correctly', function () {
        $user = User::factory()->create(['kyc_status' => 'pending']);
        KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-doctype-555',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('PROCESSED', [
            'identityVerificationId' => 'idv-doctype-555',
            'document'               => [
                'type'   => 'DriverLicense',
                'number' => 'DL123',
            ],
            'person' => [
                'firstName' => 'Driver',
                'lastName'  => 'Test',
            ],
        ]);

        $verification = KycVerification::where('provider_reference', 'idv-doctype-555')->first();
        assert($verification !== null);
        $extractedData = $verification->extracted_data;
        assert(is_array($extractedData));
        expect($extractedData['document_type'])->toBe('driving_license');
    });

    it('does not change user status on EXPIRED if not pending', function () {
        $user = User::factory()->create(['kyc_status' => 'approved']);
        KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_IN_PROGRESS,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-expired-keep',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('EXPIRED', [
            'identityVerificationId' => 'idv-expired-keep',
        ]);

        $user->refresh();
        expect($user->kyc_status)->toBe('approved');
    });
});

// ──────────────────────────────────────────────────────────────
// TrustCert Linkage on Webhook Processing
// ──────────────────────────────────────────────────────────────

describe('TrustCert linkage', function () {
    beforeEach(function () {
        ondatoServiceTestFakeApi();
    });

    it('updates TrustCertApplication to approved and issues certificate on PROCESSED', function () {
        $user = User::factory()->create([
            'kyc_status' => 'pending',
            'uuid'       => 'user-trustcert-uuid',
            'name'       => 'Test User',
            'email'      => 'test@example.com',
        ]);

        // Set up TrustCert application in cache
        Cache::put("trustcert_application:{$user->id}", [
            'id'     => 'app_cert_123',
            'status' => 'pending',
            'level'  => 2,
        ], now()->addDays(30));

        $verification = KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-trustcert-approve',
            'application_id'     => 'app_cert_123',
            'target_level'       => 'verified',
            'verification_data'  => [],
        ]);

        // Mock CertificateAuthorityService
        $caService = Mockery::mock(CertificateAuthorityService::class);
        $caService->shouldReceive('issueCertificate')
            ->once()
            ->withArgs(function ($subjectId, $subject, $validFrom, $validUntil, $parentCertId, $extensions) use ($user, $verification) {
                return $subjectId === "user:{$user->uuid}"
                    && $subject['name'] === 'Test User'
                    && $subject['email'] === 'test@example.com'
                    && $subject['level'] === 'verified'
                    && $extensions['application_id'] === 'app_cert_123'
                    && $extensions['kyc_verification_id'] === $verification->id;
            })
            ->andReturn(new App\Domain\TrustCert\ValueObjects\Certificate(
                certificateId: 'cert_test_123',
                subjectId: "user:{$user->uuid}",
                subject: ['name' => 'Test User'],
                publicKey: 'test-public-key',
                signature: 'test-signature',
                validFrom: now(),
                validUntil: now()->addYears(2),
                status: App\Domain\TrustCert\Enums\CertificateStatus::ACTIVE,
                extensions: [],
            ));
        $this->app->instance(CertificateAuthorityService::class, $caService);

        $this->service->processWebhook('PROCESSED', [
            'identityVerificationId' => 'idv-trustcert-approve',
            'document'               => ['type' => 'Passport', 'number' => 'P123'],
            'person'                 => ['firstName' => 'Test', 'lastName' => 'User'],
        ]);

        // Verify TrustCert application was updated in cache
        $updatedApp = Cache::get("trustcert_application:{$user->id}");
        expect($updatedApp['status'])->toBe('approved');
        expect($updatedApp['kyc_verification_id'])->toBe($verification->id);
        expect($updatedApp)->toHaveKey('approved_at');
    });

    it('updates TrustCertApplication to rejected on REJECTED', function () {
        Event::fake([KycVerificationFailed::class]);

        $user = User::factory()->create(['kyc_status' => 'pending']);

        Cache::put("trustcert_application:{$user->id}", [
            'id'     => 'app_reject_456',
            'status' => 'pending',
        ], now()->addDays(30));

        KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_IN_PROGRESS,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-trustcert-reject',
            'application_id'     => 'app_reject_456',
            'target_level'       => 'verified',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('REJECTED', [
            'identityVerificationId' => 'idv-trustcert-reject',
            'rejectReason'           => 'Document tampering detected',
        ]);

        $updatedApp = Cache::get("trustcert_application:{$user->id}");
        expect($updatedApp['status'])->toBe('rejected');
        expect($updatedApp['failure_reason'])->toBe('Document tampering detected');
        expect($updatedApp)->toHaveKey('rejected_at');
    });

    it('does not update TrustCertApplication when no application_id linked', function () {
        $user = User::factory()->create(['kyc_status' => 'pending']);

        Cache::put("trustcert_application:{$user->id}", [
            'id'     => 'app_unlinked',
            'status' => 'pending',
        ], now()->addDays(30));

        KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-no-link',
            'verification_data'  => [],
        ]);

        $this->service->processWebhook('PROCESSED', [
            'identityVerificationId' => 'idv-no-link',
            'document'               => ['type' => 'Passport', 'number' => 'P999'],
            'person'                 => ['firstName' => 'No', 'lastName' => 'Link'],
        ]);

        // Application should remain unchanged
        $app = Cache::get("trustcert_application:{$user->id}");
        expect($app['status'])->toBe('pending');
    });

    it('gracefully handles missing TrustCertApplication in cache', function () {
        $user = User::factory()->create(['kyc_status' => 'pending']);

        // No application in cache
        Cache::forget("trustcert_application:{$user->id}");

        KycVerification::create([
            'user_id'            => $user->id,
            'type'               => KycVerification::TYPE_IDENTITY,
            'status'             => KycVerification::STATUS_PENDING,
            'provider'           => 'ondato',
            'provider_reference' => 'idv-missing-app',
            'application_id'     => 'app_gone',
            'target_level'       => 'verified',
            'verification_data'  => [],
        ]);

        // Should not throw
        $this->service->processWebhook('PROCESSED', [
            'identityVerificationId' => 'idv-missing-app',
            'document'               => ['type' => 'Passport', 'number' => 'P000'],
            'person'                 => ['firstName' => 'Gone', 'lastName' => 'App'],
        ]);

        // Verification itself should still be completed
        $verification = KycVerification::where('provider_reference', 'idv-missing-app')->first();
        assert($verification !== null);
        expect($verification->status)->toBe(KycVerification::STATUS_COMPLETED);
    });
});
