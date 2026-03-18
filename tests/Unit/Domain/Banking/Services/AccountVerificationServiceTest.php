<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Banking\Services;

use App\Domain\Banking\Connectors\OpenBankingConnector;
use App\Domain\Banking\Exceptions\BankOperationException;
use App\Domain\Banking\Services\AccountVerificationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AccountVerificationServiceTest extends TestCase
{
    private AccountVerificationService $service;

    private AccountVerificationService $serviceWithOb;

    private OpenBankingConnector $obConnector;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->service = new AccountVerificationService();

        $this->obConnector = new OpenBankingConnector([
            'bank_code'     => 'OB_TEST',
            'bank_name'     => 'Test Open Bank',
            'base_url'      => 'https://api.openbanking.test/v1',
            'client_id'     => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'token_url'     => 'https://api.openbanking.test/v1/oauth2/token',
        ]);

        $this->serviceWithOb = new AccountVerificationService($this->obConnector);
    }

    // ---- Micro-Deposit Tests ----

    public function test_initiate_micro_deposit_returns_verification_id(): void
    {
        $result = $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        $this->assertArrayHasKey('verification_id', $result);
        $this->assertStringStartsWith('mdv_', $result['verification_id']);
        $this->assertEquals('pending', $result['status']);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_initiate_micro_deposit_stores_verification_in_cache(): void
    {
        $result = $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        $cacheKey = 'account_verification:' . $result['verification_id'];

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);

        $this->assertNotNull($cached);
        $this->assertEquals('micro_deposit', $cached['method']);
        $this->assertEquals('pending', $cached['status']);
        $this->assertCount(2, $cached['amounts']);
        $this->assertGreaterThanOrEqual(1, $cached['amounts'][0]);
        $this->assertLessThanOrEqual(99, $cached['amounts'][0]);
        $this->assertGreaterThanOrEqual(1, $cached['amounts'][1]);
        $this->assertLessThanOrEqual(99, $cached['amounts'][1]);
    }

    public function test_initiate_micro_deposit_rejects_duplicate_pending(): void
    {
        $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        $this->expectException(BankOperationException::class);
        $this->expectExceptionMessage('already pending');

        $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');
    }

    public function test_verify_micro_deposit_succeeds_with_correct_amounts(): void
    {
        $initResult = $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        // Retrieve actual amounts from cache
        $cacheKey = 'account_verification:' . $initResult['verification_id'];

        /** @var array{amounts: array{int, int}} $cached */
        $cached = Cache::get($cacheKey);
        $amounts = $cached['amounts'];

        $result = $this->service->verifyMicroDeposit($initResult['verification_id'], $amounts);

        $this->assertTrue($result['verified']);
        $this->assertEquals('verified', $result['status']);
        $this->assertStringContainsString('successfully verified', $result['message']);
    }

    public function test_verify_micro_deposit_succeeds_with_reversed_order(): void
    {
        $initResult = $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        $cacheKey = 'account_verification:' . $initResult['verification_id'];

        /** @var array{amounts: array{int, int}} $cached */
        $cached = Cache::get($cacheKey);
        $amounts = array_reverse($cached['amounts']);

        $result = $this->service->verifyMicroDeposit($initResult['verification_id'], $amounts);

        $this->assertTrue($result['verified']);
        $this->assertEquals('verified', $result['status']);
    }

    public function test_verify_micro_deposit_fails_with_wrong_amounts(): void
    {
        $initResult = $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        $result = $this->service->verifyMicroDeposit($initResult['verification_id'], [0, 0]);

        $this->assertFalse($result['verified']);
        $this->assertEquals('pending', $result['status']);
        $this->assertStringContainsString('do not match', $result['message']);
    }

    public function test_verify_micro_deposit_fails_after_max_attempts(): void
    {
        $initResult = $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        // Exhaust all 5 attempts
        for ($i = 0; $i < 5; $i++) {
            $this->service->verifyMicroDeposit($initResult['verification_id'], [0, 0]);
        }

        // 6th attempt should lock it out
        $result = $this->service->verifyMicroDeposit($initResult['verification_id'], [0, 0]);

        $this->assertFalse($result['verified']);
        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('Maximum verification attempts', $result['message']);
    }

    public function test_verify_micro_deposit_rejects_expired_verification(): void
    {
        $this->expectException(BankOperationException::class);
        $this->expectExceptionMessage('Verification not found or expired');

        $this->service->verifyMicroDeposit('mdv_non-existent-id', [10, 20]);
    }

    public function test_verify_micro_deposit_rejects_non_pending_verification(): void
    {
        $initResult = $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        // Retrieve and verify with correct amounts first
        $cacheKey = 'account_verification:' . $initResult['verification_id'];

        /** @var array{amounts: array{int, int}} $cached */
        $cached = Cache::get($cacheKey);
        $this->service->verifyMicroDeposit($initResult['verification_id'], $cached['amounts']);

        // Trying to verify again should fail (status is now 'verified')
        $this->expectException(BankOperationException::class);
        $this->expectExceptionMessage('not in pending state');

        $this->service->verifyMicroDeposit($initResult['verification_id'], $cached['amounts']);
    }

    // ---- Instant Verification Tests ----

    public function test_initiate_instant_verification_creates_consent(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/consents' => Http::response([
                'consentId'     => 'consent-xyz',
                'consentStatus' => 'received',
                '_links'        => [
                    'scaRedirect' => [
                        'href' => 'https://bank.example.com/authorize?consent=consent-xyz',
                    ],
                ],
            ], 200),
        ]);

        $result = $this->serviceWithOb->initiateInstantVerification(
            'user-123',
            'acc-001',
            'DE89370400440532013000'
        );

        $this->assertArrayHasKey('verification_id', $result);
        $this->assertStringStartsWith('ivf_', $result['verification_id']);
        $this->assertEquals('awaiting_consent', $result['status']);
        $this->assertNotNull($result['redirect_url']);
        $this->assertStringContainsString('consent-xyz', $result['redirect_url']);
    }

    public function test_initiate_instant_verification_requires_ob_connector(): void
    {
        $serviceWithoutOb = new AccountVerificationService(null);

        $this->expectException(BankOperationException::class);
        $this->expectExceptionMessage('Open Banking connector is required');

        $serviceWithoutOb->initiateInstantVerification('user-123', 'acc-001', 'DE89370400440532013000');
    }

    public function test_complete_instant_verification_succeeds_with_valid_consent(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/consents' => Http::response([
                'consentId'     => 'consent-xyz',
                'consentStatus' => 'received',
                '_links'        => [
                    'scaRedirect' => [
                        'href' => 'https://bank.example.com/authorize',
                    ],
                ],
            ], 200),
            'https://api.openbanking.test/v1/consents/consent-xyz/status' => Http::response([
                'consentStatus' => 'valid',
            ], 200),
            'https://api.openbanking.test/v1/accounts' => Http::response([
                'accounts' => [
                    [
                        'resourceId' => 'acc-001',
                        'iban'       => 'DE89370400440532013000',
                        'currency'   => 'EUR',
                        'status'     => 'active',
                    ],
                ],
            ], 200),
        ]);

        $initResult = $this->serviceWithOb->initiateInstantVerification(
            'user-123',
            'acc-001',
            'DE89370400440532013000'
        );

        $result = $this->serviceWithOb->completeInstantVerification($initResult['verification_id']);

        $this->assertTrue($result['verified']);
        $this->assertEquals('verified', $result['status']);
        $this->assertStringContainsString('successfully verified', $result['message']);
    }

    public function test_complete_instant_verification_fails_when_iban_mismatch(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/consents' => Http::response([
                'consentId'     => 'consent-xyz',
                'consentStatus' => 'received',
                '_links'        => ['scaRedirect' => ['href' => 'https://bank.example.com']],
            ], 200),
            'https://api.openbanking.test/v1/consents/consent-xyz/status' => Http::response([
                'consentStatus' => 'valid',
            ], 200),
            'https://api.openbanking.test/v1/accounts' => Http::response([
                'accounts' => [
                    [
                        'resourceId' => 'acc-999',
                        'iban'       => 'FR7630006000011234567890189',
                        'currency'   => 'EUR',
                        'status'     => 'active',
                    ],
                ],
            ], 200),
        ]);

        $initResult = $this->serviceWithOb->initiateInstantVerification(
            'user-123',
            'acc-001',
            'DE89370400440532013000'
        );

        $result = $this->serviceWithOb->completeInstantVerification($initResult['verification_id']);

        $this->assertFalse($result['verified']);
        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('does not match', $result['message']);
    }

    public function test_complete_instant_verification_returns_awaiting_when_pending(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/consents' => Http::response([
                'consentId'     => 'consent-xyz',
                'consentStatus' => 'received',
                '_links'        => ['scaRedirect' => ['href' => 'https://bank.example.com']],
            ], 200),
            'https://api.openbanking.test/v1/consents/consent-xyz/status' => Http::response([
                'consentStatus' => 'received',
            ], 200),
        ]);

        $initResult = $this->serviceWithOb->initiateInstantVerification(
            'user-123',
            'acc-001',
            'DE89370400440532013000'
        );

        $result = $this->serviceWithOb->completeInstantVerification($initResult['verification_id']);

        $this->assertFalse($result['verified']);
        $this->assertEquals('awaiting_consent', $result['status']);
        $this->assertStringContainsString('Waiting for user', $result['message']);
    }

    public function test_complete_instant_verification_fails_when_consent_rejected(): void
    {
        Http::fake([
            'https://api.openbanking.test/v1/oauth2/token' => Http::response([
                'access_token' => 'test-token',
                'expires_in'   => 3600,
            ], 200),
            'https://api.openbanking.test/v1/consents' => Http::response([
                'consentId'     => 'consent-xyz',
                'consentStatus' => 'received',
                '_links'        => ['scaRedirect' => ['href' => 'https://bank.example.com']],
            ], 200),
            'https://api.openbanking.test/v1/consents/consent-xyz/status' => Http::response([
                'consentStatus' => 'rejected',
            ], 200),
        ]);

        $initResult = $this->serviceWithOb->initiateInstantVerification(
            'user-123',
            'acc-001',
            'DE89370400440532013000'
        );

        $result = $this->serviceWithOb->completeInstantVerification($initResult['verification_id']);

        $this->assertFalse($result['verified']);
        $this->assertEquals('failed', $result['status']);
        $this->assertStringContainsString('rejected', $result['message']);
    }

    // ---- Status Tests ----

    public function test_get_verification_status_returns_details(): void
    {
        $initResult = $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        $status = $this->service->getVerificationStatus($initResult['verification_id']);

        $this->assertEquals($initResult['verification_id'], $status['verification_id']);
        $this->assertEquals('pending', $status['status']);
        $this->assertEquals('micro_deposit', $status['method']);
        $this->assertArrayHasKey('created_at', $status);
        $this->assertArrayHasKey('expires_at', $status);
        $this->assertEquals(0, $status['attempts']);
    }

    public function test_get_verification_status_throws_for_unknown_id(): void
    {
        $this->expectException(BankOperationException::class);
        $this->expectExceptionMessage('Verification not found or expired');

        $this->service->getVerificationStatus('mdv_does-not-exist');
    }

    public function test_get_verification_status_tracks_attempts(): void
    {
        $initResult = $this->service->initiateMicroDeposit('user-123', 'acc-001', 'DE89370400440532013000');

        // Make two failed attempts
        $this->service->verifyMicroDeposit($initResult['verification_id'], [0, 0]);
        $this->service->verifyMicroDeposit($initResult['verification_id'], [0, 0]);

        $status = $this->service->getVerificationStatus($initResult['verification_id']);

        $this->assertEquals(2, $status['attempts']);
        $this->assertEquals('pending', $status['status']);
    }
}
