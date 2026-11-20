<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Compliance\Models\KycVerification;
use App\Domain\Compliance\Services\OndatoService;
use App\Domain\Custodian\Contracts\ICustodianConnector;
use App\Domain\Custodian\Exceptions\CustodianNotFoundException;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Services\CustodianRegistry;
use App\Domain\MachinePay\DataObjects\MppCredential;
use App\Domain\MachinePay\DataObjects\MppReceipt;
use App\Domain\MachinePay\Models\MppMonetizedResource;
use App\Domain\MachinePay\Services\MppSettlementService;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\SMS\Services\SmsSendBudget;
use App\Domain\SMS\Services\SmsService;
use App\Domain\TrustCert\Events\CertificateIssued;
use App\Domain\TrustCert\Services\CertificateAuthorityService;
use App\Domain\TrustCert\ValueObjects\Certificate;
use App\Domain\Wallet\Exceptions\AddressRegistrationRejectedException;
use App\Http\Middleware\SmsSendGateMiddleware;
use App\Models\User;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/*
|--------------------------------------------------------------------------
| GHSA-rrfq follow-ups: compliance and operations cluster
|--------------------------------------------------------------------------
|
|  - C10/C42: ops:verify-env checks ONDATO_WEBHOOK_SECRET when Ondato is
|    configured (SKIP otherwise); the env templates list it.
|  - C42: Ondato webhooks never move a final verification back, and an
|    approval reads its result and identity fields from the Ondato API.
|  - C37: TrustCert export PDFs live on the private disk and download
|    through a signed, holder-only route.
|  - Custodian transfer failures answer a generic message.
|  - D02: SMS daily limits count billed parts; paid sends have a daily
|    ceiling, checked before an MPP payment is settled.
|  - C40: a caller-supplied smart-account owner is checked against the
|    caller's Privy wallets (monitor logs, enforce refuses).
*/

// ── Helpers ──────────────────────────────────────────────────────────────

beforeEach(function (): void {
    // Keep counters, rate limiters and IP blocks out of the Redis cache that
    // CI's parallel processes share.
    Tests\Traits\SharedCacheIsolation::isolate();
});

/**
 * @return array{name: string, category: string, result: string, detail: string}
 */
function rrfqCoOpsCheck(string $name): array
{
    Artisan::call('ops:verify-env', ['--json' => true]);

    /** @var array{checks: list<array{name: string, category: string, result: string, detail: string}>} $decoded */
    $decoded = json_decode(Artisan::output(), true);

    return collect($decoded['checks'])->keyBy('name')->get($name) ?? ['name' => $name, 'category' => '', 'result' => 'MISSING', 'detail' => ''];
}

function rrfqCoOndatoConfig(): void
{
    config([
        'services.ondato.application_id'  => 'rrfq-app',
        'services.ondato.secret'          => 'rrfq-secret',
        'services.ondato.setup_id'        => 'rrfq-setup',
        'services.ondato.sandbox'         => true,
        'services.ondato.webhook_secret'  => 'rrfq-webhook-secret',
        'services.ondato.kyc_api_url'     => 'https://sandbox-kycapi.ondato.com',
        'services.ondato.verifid_api_url' => 'https://verifid.ondato.com',
    ]);
    Cache::forget('ondato:access_token');
}

/**
 * Fake the Ondato API: the identity verification record is $record.
 *
 * @param  array<string, mixed>  $record
 */
function rrfqCoOndatoApi(array $record): void
{
    Http::fake(function (HttpRequest $request) use ($record) {
        if (str_contains($request->url(), '/v3/oauth/token')) {
            return Http::response(['access_token' => 'rrfq-token', 'expires_in' => 3600]);
        }

        if (str_contains($request->url(), '/v1/identity-verifications/')) {
            return Http::response($record);
        }

        return Http::response([], 404);
    });
}

function rrfqCoVerification(User $user, string $status, string $reference): KycVerification
{
    return KycVerification::create([
        'user_id'            => $user->id,
        'type'               => KycVerification::TYPE_IDENTITY,
        'status'             => $status,
        'provider'           => 'ondato',
        'provider_reference' => $reference,
        'verification_data'  => [],
    ]);
}

function rrfqCoIssueCertificate(User $holder): Certificate
{
    Event::fake([CertificateIssued::class]);

    return app(CertificateAuthorityService::class)->issueCertificate(
        subjectId: "user:{$holder->uuid}",
        subject: ['name' => 'Holder', 'type' => 'individual'],
        validFrom: now()->subDay(),
        validUntil: now()->addYear(),
    );
}

/**
 * @return TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function rrfqCoExport(PHPUnitTestCase $test, Certificate $certificate): TestResponse
{
    return $test->postJson("/api/v1/trustcert/{$certificate->certificateId}/export-pdf");
}

function rrfqCoExportFile(string $pdfUrl): string
{
    if (preg_match('#/export-pdf/([A-Za-z0-9]{40})\?#', $pdfUrl, $match) !== 1) {
        throw new RuntimeException("No export file in {$pdfUrl}");
    }

    return $match[1];
}

/**
 * @return SmsService&MockInterface
 */
function rrfqCoMockSms(int $times): MockInterface
{
    /** @var SmsService&MockInterface $sms */
    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('send')->times($times)->andReturn([
        'message_id'  => 'rrfq-msg-1',
        'status'      => 'sent',
        'parts'       => 1,
        'destination' => '+37069912345',
        'price_usdc'  => '48438',
    ]);
    app()->instance(SmsService::class, $sms);

    return $sms;
}

/**
 * Run the SMS gate directly on a request already paid on a protocol
 * subdomain.
 *
 * @param  array<string, mixed>  $payment
 */
function rrfqCoPaidSend(array $payment, string $message, int $status, bool &$reached): int
{
    $reached = false;
    $request = Request::create('/api/v1/sms/send', 'POST', ['to' => '+37069912345', 'message' => $message]);
    $request->attributes->set('x402_payment', $payment);

    $response = app(SmsSendGateMiddleware::class)->handle($request, function () use ($status, &$reached): JsonResponse {
        $reached = true;

        return response()->json(['sent' => true], $status);
    });

    return $response->getStatusCode();
}

function rrfqCoPaymentHeader(): string
{
    return 'Payment ' . (new MppCredential(
        challengeId: 'ch_rrfq',
        rail: 'stripe',
        proofOfPayment: ['spt' => 'spt_rrfq'],
        payerIdentifier: 'agent_rrfq',
        timestamp: gmdate('Y-m-d\TH:i:s\Z'),
    ))->toBase64Url();
}

function rrfqCoMonetizeSms(): void
{
    MppMonetizedResource::query()->create([
        'method'          => 'POST',
        'path'            => 'api/v1/sms/send',
        'amount_cents'    => 5,
        'currency'        => 'USD',
        'available_rails' => ['stripe'],
        'description'     => 'SMS send',
        'is_active'       => true,
    ]);
}

/**
 * An account owned by a new user, linked to custodian account mock-account-1.
 *
 * @return array{0: Account, 1: User}
 */
function rrfqCoLinkedAccount(): array
{
    $owner = User::factory()->create();
    $account = Account::factory()->withBalance(5000)->create(['user_uuid' => $owner->uuid]);
    CustodianAccount::create([
        'account_uuid'         => $account->uuid,
        'custodian_name'       => 'mock',
        'custodian_account_id' => 'mock-account-1',
        'status'               => 'active',
        'is_primary'           => true,
    ]);

    return [$account, $owner];
}

function rrfqCoEvm(): string
{
    return '0x' . bin2hex(random_bytes(20));
}

/**
 * Answer Privy's GET /api/v1/users/{id} with these linked accounts. With no
 * responses queued, any call to Privy fails.
 *
 * @param  list<array<string, mixed>>|null  $linked
 */
function rrfqCoFakePrivy(?array $linked): void
{
    config(['privy.app_id' => 'privy-app', 'privy.app_secret' => 'privy-secret']);
    $responses = $linked === null
        ? []
        : [new GuzzleResponse(200, [], (string) json_encode(['id' => 'did:privy:rrfq', 'linked_accounts' => $linked]))];
    app()->instance(ClientInterface::class, new Client(['handler' => HandlerStack::create(new MockHandler($responses))]));
}

// ── C10/C42: ops:verify-env and the env templates ────────────────────────

describe('C10/C42 Ondato webhook secret in ops:verify-env', function (): void {
    it('skips the check when Ondato is not configured', function (): void {
        config([
            'services.ondato.application_id' => null,
            'services.ondato.secret'         => null,
            'services.ondato.webhook_secret' => null,
        ]);

        $check = rrfqCoOpsCheck('services.ondato.webhook_secret');

        expect($check['result'])->toBe('SKIP')
            ->and($check['category'])->toBe('bypasses');
    });

    it('fails when Ondato is configured without a webhook secret', function (array $credentials): void {
        config($credentials + ['services.ondato.webhook_secret' => '']);

        $check = rrfqCoOpsCheck('services.ondato.webhook_secret');

        expect($check['result'])->toBe('FAIL')
            ->and($check['detail'])->toContain('ONDATO_WEBHOOK_SECRET');
    })->with([
        'application id' => [['services.ondato.application_id' => 'app', 'services.ondato.secret' => '']],
        'secret'         => [['services.ondato.application_id' => '', 'services.ondato.secret' => 'secret']],
    ]);

    it('passes when Ondato is configured with a webhook secret', function (): void {
        config([
            'services.ondato.application_id' => 'app',
            'services.ondato.secret'         => 'secret',
            'services.ondato.webhook_secret' => 'whsec',
        ]);

        expect(rrfqCoOpsCheck('services.ondato.webhook_secret')['result'])->toBe('PASS');
    });

    it('lists ONDATO_WEBHOOK_SECRET in the production and Zelta env templates', function (string $template): void {
        expect((string) file_get_contents(base_path($template)))->toMatch('/^ONDATO_WEBHOOK_SECRET=$/m');
    })->with(['.env.production.example', '.env.zelta.example']);
});

// ── C42: Ondato status regressions and API-sourced identity ──────────────

describe('C42 Ondato webhook processing', function (): void {
    beforeEach(function (): void {
        rrfqCoOndatoConfig();
    });

    it('takes the identity fields from the Ondato API, not from the webhook body', function (): void {
        $user = User::factory()->create(['kyc_status' => 'pending']);
        $verification = rrfqCoVerification($user, KycVerification::STATUS_PENDING, 'idv-rrfq-api');
        rrfqCoOndatoApi([
            'id'                  => 'idv-rrfq-api',
            'status'              => 'Approved',
            'externalReferenceId' => $user->uuid,
            'document'            => ['type' => 'Passport', 'number' => 'P-REAL-1', 'issuingCountry' => 'LT'],
            'person'              => ['firstName' => 'Jonas', 'lastName' => 'Jonaitis'],
        ]);

        app(OndatoService::class)->processWebhook('PROCESSED', [
            'identityVerificationId' => 'idv-rrfq-api',
            'status'                 => 'PROCESSED',
            'document'               => ['type' => 'IdCard', 'number' => 'FORGED-9'],
            'person'                 => ['firstName' => 'Mallory', 'lastName' => 'Forged'],
        ]);

        $verification->refresh();
        expect($verification->status)->toBe(KycVerification::STATUS_COMPLETED)
            ->and($verification->first_name)->toBe('Jonas')
            ->and($verification->last_name)->toBe('Jonaitis')
            ->and($verification->document_number)->toBe('P-REAL-1')
            ->and($verification->document_type)->toBe('passport')
            ->and(json_encode($verification->verification_data))->not->toContain('Mallory')
            ->and($user->fresh()?->kyc_status)->toBe('approved');
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/v1/identity-verifications/idv-rrfq-api'));
    });

    it('does not approve when the Ondato API does not confirm the approval or names another user', function (array $record): void {
        $user = User::factory()->create(['kyc_status' => 'pending']);
        $verification = rrfqCoVerification($user, KycVerification::STATUS_PENDING, 'idv-rrfq-unconfirmed');
        rrfqCoOndatoApi($record + ['id' => 'idv-rrfq-unconfirmed']);

        app(OndatoService::class)->processWebhook('PROCESSED', [
            'identityVerificationId' => 'idv-rrfq-unconfirmed',
            'person'                 => ['firstName' => 'Mallory'],
        ]);

        $verification->refresh();
        expect($verification->status)->toBe(KycVerification::STATUS_PENDING)
            ->and($verification->first_name)->toBeNull()
            ->and($verification->completed_at)->toBeNull()
            ->and($user->fresh()?->kyc_status)->toBe('pending');
    })->with([
        'API says rejected'        => [['status' => 'Rejected']],
        'API has no status'        => [[]],
        'API names another person' => [['status' => 'Approved', 'externalReferenceId' => 'someone-else']],
    ]);

    it('refuses to move a final verification back or to approve it again', function (string $from, string $userStatus, string $event): void {
        $user = User::factory()->create(['kyc_status' => $userStatus, 'kyc_expires_at' => now()->addMonth()]);
        $verification = rrfqCoVerification($user, $from, 'idv-rrfq-final');
        $expiresAt = $user->fresh()?->getRawOriginal('kyc_expires_at');
        Http::fake();

        app(OndatoService::class)->processWebhook($event, [
            'identityVerificationId' => 'idv-rrfq-final',
            'person'                 => ['firstName' => 'Mallory'],
        ]);

        expect($verification->fresh()?->status)->toBe($from)
            ->and($verification->fresh()?->first_name)->toBeNull()
            ->and($user->fresh()?->kyc_status)->toBe($userStatus)
            ->and($user->fresh()?->getRawOriginal('kyc_expires_at'))->toBe($expiresAt);
        Http::assertNothingSent();
    })->with([
        'approved, then approved again' => [KycVerification::STATUS_COMPLETED, 'approved', 'CROSS_CHECKED'],
        'approved, then expired'        => [KycVerification::STATUS_COMPLETED, 'approved', 'EXPIRED'],
        'approved, then started'        => [KycVerification::STATUS_COMPLETED, 'approved', 'STARTED'],
        'rejected, then approved'       => [KycVerification::STATUS_FAILED, 'rejected', 'PROCESSED'],
        'expired, then approved'        => [KycVerification::STATUS_EXPIRED, 'expired', 'PROCESSED'],
        'rejected, then expired'        => [KycVerification::STATUS_FAILED, 'rejected', 'EXPIRED'],
    ]);

    it('still moves an open verification forward and lets a rejection follow an approval', function (string $from, string $event, string $to, string $userTo): void {
        $user = User::factory()->create(['kyc_status' => $from === KycVerification::STATUS_COMPLETED ? 'approved' : 'pending']);
        $verification = rrfqCoVerification($user, $from, 'idv-rrfq-open');

        app(OndatoService::class)->processWebhook($event, [
            'identityVerificationId' => 'idv-rrfq-open',
            'rejectReason'           => 'Document tampering',
        ]);

        expect($verification->fresh()?->status)->toBe($to)
            ->and($user->fresh()?->kyc_status)->toBe($userTo);
    })->with([
        'pending, then started'      => [KycVerification::STATUS_PENDING, 'STARTED', KycVerification::STATUS_IN_PROGRESS, 'pending'],
        'in progress, then expired'  => [KycVerification::STATUS_IN_PROGRESS, 'EXPIRED', KycVerification::STATUS_EXPIRED, 'expired'],
        'in progress, then rejected' => [KycVerification::STATUS_IN_PROGRESS, 'REJECTED', KycVerification::STATUS_FAILED, 'rejected'],
        'approved, then rejected'    => [KycVerification::STATUS_COMPLETED, 'REJECTED', KycVerification::STATUS_FAILED, 'rejected'],
    ]);
});

// ── C37: TrustCert export PDFs ───────────────────────────────────────────

describe('C37 TrustCert export PDFs', function (): void {
    beforeEach(function (): void {
        Storage::fake('public');
        Storage::fake('local');
    });

    it('keeps the export off the public disk and lets the holder download it with the same response shape', function (): void {
        $holder = User::factory()->create();
        $certificate = rrfqCoIssueCertificate($holder);
        Sanctum::actingAs($holder, ['read', 'write', 'delete']);

        $response = rrfqCoExport($this, $certificate)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'data' => ['pdfUrl', 'expiresAt']]);

        $pdfUrl = (string) $response->json('data.pdfUrl');
        $path = 'trustcert-exports/' . rrfqCoExportFile($pdfUrl) . '.pdf';

        expect(Storage::disk('public')->allFiles())->toBe([])
            ->and(Storage::disk('local')->allFiles())->toBe([$path])
            ->and($pdfUrl)->toContain('signature=');

        $download = $this->get($pdfUrl)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        expect($download->streamedContent())->toBe(Storage::disk('local')->get($path))
            ->and($download->streamedContent())->toStartWith('%PDF');
    });

    it('answers 404 to another user who has the holder\'s signed link, and keeps the file', function (): void {
        $holder = User::factory()->create();
        $certificate = rrfqCoIssueCertificate($holder);
        Sanctum::actingAs($holder, ['read', 'write', 'delete']);
        $pdfUrl = (string) rrfqCoExport($this, $certificate)->assertOk()->json('data.pdfUrl');

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $response = $this->getJson($pdfUrl)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'EXPORT_NOT_FOUND');

        expect($response->getContent())->not->toContain('%PDF')
            ->and(Storage::disk('local')->allFiles())->toHaveCount(1);
    });

    it('refuses an unsigned, tampered or expired link even for the holder', function (): void {
        $holder = User::factory()->create();
        $certificate = rrfqCoIssueCertificate($holder);
        Sanctum::actingAs($holder, ['read', 'write', 'delete']);
        $pdfUrl = (string) rrfqCoExport($this, $certificate)->assertOk()->json('data.pdfUrl');
        $file = rrfqCoExportFile($pdfUrl);

        $this->getJson("/api/v1/trustcert/{$certificate->certificateId}/export-pdf/{$file}")
            ->assertForbidden()
            ->assertJsonPath('error.code', 'INVALID_DOWNLOAD_LINK');
        $this->getJson(str_replace($file, str_repeat('A', 40), $pdfUrl))->assertForbidden();

        $this->travel(61)->minutes();
        $this->getJson($pdfUrl)->assertForbidden();
    });

    it('requires authentication for the download, even with a valid signature', function (): void {
        $certificate = rrfqCoIssueCertificate(User::factory()->create());
        $file = str_repeat('C', 40);
        Storage::disk('local')->put("trustcert-exports/{$file}.pdf", '%PDF-holder');
        $pdfUrl = URL::temporarySignedRoute('api.trustcert.certificate.download', now()->addHour(), [
            'certId' => $certificate->certificateId,
            'file'   => $file,
        ]);

        $this->getJson($pdfUrl)->assertUnauthorized();
    });

    it('deletes expired exports and the PDFs earlier releases left on the public disk', function (): void {
        $holder = User::factory()->create();
        $certificate = rrfqCoIssueCertificate($holder);
        Storage::disk('public')->put('certs/legacy.pdf', '%PDF-legacy');
        Storage::disk('local')->put('trustcert-exports/' . str_repeat('B', 40) . '.pdf', '%PDF-old');
        Storage::disk('public')->put('certs/recent.pdf', '%PDF-recent');
        touch(Storage::disk('public')->path('certs/legacy.pdf'), time() - 7200);
        touch(Storage::disk('local')->path('trustcert-exports/' . str_repeat('B', 40) . '.pdf'), time() - 7200);

        Sanctum::actingAs($holder, ['read', 'write', 'delete']);
        $pdfUrl = (string) rrfqCoExport($this, $certificate)->assertOk()->json('data.pdfUrl');

        expect(Storage::disk('public')->allFiles())->toBe(['certs/recent.pdf'])
            ->and(Storage::disk('local')->allFiles())->toBe(['trustcert-exports/' . rrfqCoExportFile($pdfUrl) . '.pdf']);
    });
});

// ── Custodian transfer error text ────────────────────────────────────────

describe('custodian transfer error text', function (): void {
    beforeEach(function (): void {
        config(['ledger.direct_funding_enabled' => true]);
    });

    it('answers a generic message instead of the connector error and logs the details', function (): void {
        [$account, $owner] = rrfqCoLinkedAccount();
        $connector = Mockery::mock(ICustodianConnector::class);
        $connector->shouldReceive('validateAccount')->once()
            ->andThrow(new RuntimeException('Upstream 500: {"iban":"LT00 SECRET","token":"sk_live_upstream"}'));
        $registry = Mockery::mock(CustodianRegistry::class);
        $registry->shouldReceive('get')->once()->with('mock')->andReturn($connector);
        app()->instance(CustodianRegistry::class, $registry);
        $logSpy = Log::spy();

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $response = $this->postJson('/api/custodians/mock/transfer', [
            'internal_account_uuid' => $account->uuid,
            'custodian_account_id'  => 'mock-account-1',
            'asset_code'            => 'USD',
            'amount'                => 10,
            'direction'             => 'withdraw',
        ])->assertStatus(400)
            ->assertExactJson(['error' => 'Transfer failed', 'message' => 'The custodian request failed.']);

        expect($response->getContent())->not->toContain('Upstream')
            ->and($response->getContent())->not->toContain('sk_live_upstream')
            ->and($account->fresh()?->getBalance('USD'))->toBe(5000);
        $logSpy->shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []): bool => $message === 'Custodian transfer failed'
                && ($context['custodian'] ?? null) === 'mock'
                && ($context['exception'] ?? null) === RuntimeException::class)
            ->once();
    });

    it('still tells the owner when the custodian itself is unknown or unavailable', function (): void {
        [$account, $owner] = rrfqCoLinkedAccount();
        $registry = Mockery::mock(CustodianRegistry::class);
        $registry->shouldReceive('get')->once()->andThrow(new CustodianNotFoundException("Custodian 'mock' not found"));
        app()->instance(CustodianRegistry::class, $registry);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->postJson('/api/custodians/mock/transfer', [
            'internal_account_uuid' => $account->uuid,
            'custodian_account_id'  => 'mock-account-1',
            'asset_code'            => 'USD',
            'amount'                => 10,
            'direction'             => 'withdraw',
        ])->assertStatus(400)
            ->assertExactJson(['error' => 'Transfer failed', 'message' => "Custodian 'mock' not found"]);
    });
});

// ── D02: SMS billed parts and the paid ceiling ───────────────────────────

describe('D02 SMS billed parts and paid ceiling', function (): void {
    beforeEach(function (): void {
        config([
            'sms.enabled'                          => true,
            'sms.global_daily_limit'               => 500,
            'sms.rate_limit.per_user_per_minute'   => 50,
            'sms.rate_limit.per_user_per_day'      => 50,
            'sms.rate_limit.paid_per_user_per_day' => 500,
            'sms.min_paid_price_usdc'              => 10000,
            'machinepay.enabled'                   => false,
        ]);
    });

    it('counts GSM-7 and UCS-2 messages in billed parts', function (string $message, int $parts): void {
        expect(SmsSendBudget::billedParts($message))->toBe($parts);
    })->with([
        'GSM-7, 160'          => [str_repeat('a', 160), 1],
        'GSM-7, 161'          => [str_repeat('a', 161), 2],
        'GSM-7, 306'          => [str_repeat('a', 306), 2],
        'GSM-7, 307'          => [str_repeat('a', 307), 3],
        'GSM-7 extension, 80' => [str_repeat('€', 80), 1],
        'GSM-7 extension, 81' => [str_repeat('€', 81), 2],
        'UCS-2, 70'           => [str_repeat('ą', 70), 1],
        'UCS-2, 71'           => [str_repeat('ą', 71), 2],
        'UCS-2, 134'          => [str_repeat('ą', 134), 2],
        'UCS-2, 135'          => [str_repeat('ą', 135), 3],
        'UCS-2, 1600'         => [str_repeat('ą', 1600), 24],
    ]);

    it('refuses an unpaid send whose billed parts pass the per-user limit and counts nothing for it', function (): void {
        config(['sms.rate_limit.per_user_per_day' => 4]);
        rrfqCoMockSms(times: 1);
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => str_repeat('a', 400)])->assertOk();
        $this->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => str_repeat('a', 400)])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'ERR_SMS_001');

        expect((int) Cache::get(SmsSendBudget::userCountCacheKey($user->id)))->toBe(3)
            ->and((int) Cache::get(SmsSendBudget::globalCountCacheKey()))->toBe(3);
    });

    it('still sends a message that fits the remaining unpaid parts', function (): void {
        config(['sms.rate_limit.per_user_per_day' => 4]);
        rrfqCoMockSms(times: 2);
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => str_repeat('a', 400)])->assertOk();
        $this->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => 'one part'])->assertOk();

        expect((int) Cache::get(SmsSendBudget::userCountCacheKey($user->id)))->toBe(4);
    });

    it('counts the global unpaid cap in billed parts', function (): void {
        config(['sms.global_daily_limit' => 5]);
        rrfqCoMockSms(times: 1);

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => str_repeat('ą', 200)])->assertOk();

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => str_repeat('ą', 200)])
            ->assertStatus(429);

        expect((int) Cache::get(SmsSendBudget::globalCountCacheKey()))->toBe(3);
    });

    it('stops a paid payer at the daily ceiling without sending', function (): void {
        config(['sms.rate_limit.paid_per_user_per_day' => 5]);
        $payment = ['payer' => '0xPayerA', 'transaction' => '0x1', 'amount' => '0.05'];
        $reached = false;

        expect(rrfqCoPaidSend($payment, str_repeat('a', 400), 200, $reached))->toBe(200)
            ->and($reached)->toBeTrue()
            ->and(rrfqCoPaidSend($payment, str_repeat('a', 400), 200, $reached))->toBe(429)
            ->and($reached)->toBeFalse()
            ->and((int) Cache::get(SmsSendBudget::paidCountCacheKey('payer:0xpayera')))->toBe(3)
            ->and(Cache::get(SmsSendBudget::globalCountCacheKey()))->toBeNull();
    });

    it('lets another payer send and gives back the parts of a failed paid send', function (): void {
        config(['sms.rate_limit.paid_per_user_per_day' => 5]);
        $reached = false;

        expect(rrfqCoPaidSend(['payer' => '0xPayerA', 'transaction' => '0x1', 'amount' => '0.05'], str_repeat('a', 400), 200, $reached))->toBe(200)
            ->and(rrfqCoPaidSend(['payer' => '0xPayerB', 'transaction' => '0x2', 'amount' => '0.05'], str_repeat('a', 400), 200, $reached))->toBe(200)
            ->and($reached)->toBeTrue()
            ->and(rrfqCoPaidSend(['payer' => '0xPayerB', 'transaction' => '0x3', 'amount' => '0.05'], 'hi', 502, $reached))->toBe(502)
            ->and((int) Cache::get(SmsSendBudget::paidCountCacheKey('payer:0xpayerb')))->toBe(3);
    });

    it('refuses an MPP payer at the ceiling before the payment is settled', function (): void {
        config(['machinepay.enabled' => true, 'sms.rate_limit.paid_per_user_per_day' => 5]);
        rrfqCoMonetizeSms();
        Cache::put(SmsSendBudget::paidCountCacheKey('ip:127.0.0.1'), 5, now()->addDay());
        $settlement = Mockery::mock(MppSettlementService::class);
        $settlement->shouldNotReceive('settle');
        app()->instance(MppSettlementService::class, $settlement);
        rrfqCoMockSms(times: 0);

        $this->withHeader('Authorization', rrfqCoPaymentHeader())
            ->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => 'hi'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'ERR_SMS_001');

        expect((int) Cache::get(SmsSendBudget::paidCountCacheKey('ip:127.0.0.1')))->toBe(5);
    });

    it('still settles and sends a paid MPP request under the ceiling', function (): void {
        config(['machinepay.enabled' => true, 'sms.rate_limit.paid_per_user_per_day' => 5]);
        rrfqCoMonetizeSms();
        $settlement = Mockery::mock(MppSettlementService::class);
        $settlement->shouldReceive('settle')->once()->andReturn(new MppReceipt(
            receiptId: 'rcpt_rrfq',
            challengeId: 'ch_rrfq',
            rail: 'stripe',
            settlementReference: 'pi_rrfq',
            settledAt: gmdate('c'),
            amountCents: 5,
            currency: 'USD',
        ));
        app()->instance(MppSettlementService::class, $settlement);
        rrfqCoMockSms(times: 1);

        $this->withHeader('Authorization', rrfqCoPaymentHeader())
            ->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => 'hi'])
            ->assertOk()
            ->assertHeader('Payment-Receipt');

        expect((int) Cache::get(SmsSendBudget::paidCountCacheKey('ip:127.0.0.1')))->toBe(1)
            ->and(Cache::get(SmsSendBudget::globalCountCacheKey()))->toBeNull();
    });
});

// ── C40: smart-account owner ownership ───────────────────────────────────

describe('C40 smart-account owner ownership', function (): void {
    it('refuses in enforce mode an owner that is not one of the caller\'s Privy wallets, with the generic 422', function (bool $privyReachable): void {
        config(['wallet.address_registration.ownership_verification' => 'enforce']);
        $user = User::factory()->create(['privy_user_id' => 'did:privy:rrfq']);
        rrfqCoFakePrivy($privyReachable ? [['type' => 'wallet', 'chain_type' => 'ethereum', 'address' => '0x' . str_repeat('1', 40)]] : null);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/relayer/account', ['owner_address' => rrfqCoEvm(), 'network' => 'polygon'])
            ->assertStatus(422)
            ->assertExactJson([
                'success' => false,
                'error'   => ['code' => 'INVALID_ADDRESS', 'message' => AddressRegistrationRejectedException::MESSAGE],
            ]);

        expect(SmartAccount::where('user_id', $user->id)->count())->toBe(0);
    })->with([
        'the caller has another wallet' => [true],
        'Privy not reachable'           => [false],
    ]);

    it('creates the account in enforce mode for the caller\'s own Privy wallet with the unchanged response', function (): void {
        config(['wallet.address_registration.ownership_verification' => 'enforce']);
        $user = User::factory()->create(['privy_user_id' => 'did:privy:rrfq']);
        $owner = rrfqCoEvm();
        // Privy may return the checksummed (mixed-case) form.
        rrfqCoFakePrivy([['type' => 'wallet', 'chain_type' => 'ethereum', 'address' => '0x' . strtoupper(substr($owner, 2))]]);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/relayer/account', ['owner_address' => $owner, 'network' => 'polygon'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.owner_address', strtolower($owner))
            ->assertJsonStructure(['success', 'data' => ['owner_address', 'account_address', 'network', 'deployed', 'nonce', 'pending_ops']]);

        expect(SmartAccount::where('user_id', $user->id)->count())->toBe(1);
    });

    it('does not ask Privy for the server-derived onboarding owner or an owner the user already holds', function (): void {
        config(['wallet.address_registration.ownership_verification' => 'enforce']);
        $user = User::factory()->create(['privy_user_id' => 'did:privy:rrfq']);
        rrfqCoFakePrivy(null); // any Privy call would fail the request in enforce mode
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        // The mobile app sends only the network.
        $owner = (string) $this->postJson('/api/v1/relayer/account', ['network' => 'polygon'])
            ->assertOk()
            ->json('data.owner_address');
        $this->postJson('/api/v1/relayer/account', ['owner_address' => $owner, 'network' => 'base'])
            ->assertOk()
            ->assertJsonPath('data.owner_address', $owner);

        expect(SmartAccount::where('user_id', $user->id)->count())->toBe(2);
    });

    it('keeps creating the account in monitor mode and only logs the mismatch', function (): void {
        config(['wallet.address_registration.ownership_verification' => 'monitor']);
        $user = User::factory()->create(['privy_user_id' => 'did:privy:rrfq']);
        rrfqCoFakePrivy([['type' => 'wallet', 'chain_type' => 'ethereum', 'address' => '0x' . str_repeat('2', 40)]]);
        $owner = rrfqCoEvm();
        $logSpy = Log::spy();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/relayer/account', ['owner_address' => $owner, 'network' => 'polygon'])
            ->assertOk()
            ->assertJsonPath('data.owner_address', strtolower($owner));

        expect(SmartAccount::where('user_id', $user->id)->where('owner_address', strtolower($owner))->exists())->toBeTrue();
        $logSpy->shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context = []): bool => str_contains($message, 'Smart account owner is not a wallet')
                && ($context['owner_address'] ?? null) === strtolower($owner))
            ->once();
    });
});
