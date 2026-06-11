<?php

declare(strict_types=1);

use App\Domain\Subscription\Models\IapReceipt;
use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\IapSubscriptionEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    // Configure the IAP product ids so plan resolution works in tests.
    config([
        'subscription.iap.apple.bundle_id'   => 'app.zelta',
        'subscription.iap.apple.product_ids' => [
            'monthly_pro' => 'zelta_pro_monthly',
            'annual_pro'  => 'zelta_pro_annual',
        ],
        'subscription.iap.google.product_ids' => [
            'monthly_pro' => 'zelta_pro_monthly',
            'annual_pro'  => 'zelta_pro_annual',
        ],
        'subscription.iap.google.package_name'         => 'app.zelta',
        'subscription.iap.google.webhook_audience'     => '',
        'subscription.iap.google.service_account_path' => null,
        'subscription.iap.google.service_account_json' => null,
        'subscription.iap.receipt_pepper'              => 'test-pepper',
    ]);
});

/**
 * Build a (local/testing) Apple JWS payload string. Verifier doesn't check
 * signatures in local/testing — only the payload JSON is read.
 *
 * @param array<string, mixed> $payloadOverrides
 */
function makeAppleJws(array $payloadOverrides = []): string
{
    $payload = array_merge([
        'bundleId'              => 'app.zelta',
        'originalTransactionId' => 'apple-orig-tx-001',
        'transactionId'         => 'apple-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
        'price'                 => 499,
        'purchaseDate'          => 1_715_000_000_000,
        'expiresDate'           => 1_717_000_000_000,
        'inAppOwnershipType'    => 'PURCHASED',
        'environment'           => 'Sandbox',
        'type'                  => 'Auto-Renewable Subscription',
    ], $payloadOverrides);

    $header = base64UrlEncode((string) json_encode(['alg' => 'ES256', 'x5c' => []]));
    $payloadB64 = base64UrlEncode((string) json_encode($payload));
    $signature = base64UrlEncode('signature-placeholder');

    return "{$header}.{$payloadB64}.{$signature}";
}

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

it('verifies an Apple StoreKit 2 receipt and creates iap_subscriptions + iap_receipts + outbox', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(),
        'originalTransactionId' => 'apple-orig-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'appVersion'            => '1.3.0',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-apple-verify-aaaaaaaaaaaaaaaa',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('tier', 'pro');
    $response->assertJsonPath('source', 'apple_iap');
    $response->assertJsonPath('reactivated', false);

    $sub = IapSubscription::query()->where('user_id', $user->id)->first();
    expect($sub)->not()->toBeNull();
    expect($sub->store)->toBe('apple');
    expect($sub->original_transaction_id)->toBe('apple-orig-tx-001');

    $receipt = IapReceipt::query()->where('iap_subscription_id', $sub->id)->first();
    expect($receipt)->not()->toBeNull();
    expect($receipt->amount_smallest_unit)->toBe(499);
    expect($receipt->amount_decimals)->toBe(2);
    expect($receipt->amount_currency)->toBe('EUR');
    expect($receipt->environment)->toBe('sandbox');

    $outbox = RevenueOutboxEvent::query()->first();
    expect($outbox)->not()->toBeNull();
    expect($outbox->source_type)->toBe('apple_iap');
    expect($outbox->event_kind)->toBe('iap_subscription_initial');
});

it('verifies a Google Play receipt using the local-bypass verifier path', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'   => 'google_play',
        'receipt'    => 'google-purchase-token-001',
        'productId'  => 'zelta_pro_annual',
        'appVersion' => '1.3.0',
        'currency'   => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-google-verify-bbbbbbbbbbbbbbbb',
    ]);

    $response->assertStatus(200);
    $response->assertJsonPath('source', 'google_play');

    $sub = IapSubscription::query()->where('user_id', $user->id)->first();
    expect($sub->store)->toBe('google');
    expect($sub->play_subscription_resource_id)->toStartWith('synthetic-');

    $outbox = RevenueOutboxEvent::query()->first();
    expect($outbox->source_type)->toBe('google_play');
});

it('returns ERR_SUB_001 for an unknown productId', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(),
        'originalTransactionId' => 'apple-orig-tx-002',
        'productId'             => 'zelta_pro_quarterly', // not in config map
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-bad-product-cccccccccccccccc',
    ]);

    // Validation rejects unknown product IDs before reaching the service
    // (the request rules enforce `in:zelta_pro_monthly,zelta_pro_annual`).
    $response->assertStatus(422);
});

it('returns ERR_CUR_001 for a non-EUR currency', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(['currency' => 'USD']),
        'originalTransactionId' => 'apple-orig-tx-003',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'USD',
    ], [
        'Idempotency-Key' => 'idem-bad-currency-dddddddddddddddd',
    ]);

    $response->assertStatus(400);
    $response->assertJsonPath('error.code', 'ERR_CUR_001');
});

it('returns ERR_SUB_002 kind=family_sharing_unsupported for an Apple Family Sharing receipt', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $jws = makeAppleJws([
        'originalTransactionId' => 'apple-family-tx-001',
        'inAppOwnershipType'    => 'FAMILY_SHARED',
    ]);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-family-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-family-share-eeeeeeeeeeeeeeee',
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error.code', 'ERR_SUB_002');
    $response->assertJsonPath('error.conflict.kind', 'family_sharing_unsupported');
    $response->assertJsonPath('error.conflict.attemptedSource', 'apple_iap');
    $response->assertJsonPath('error.conflict.existingSubscription.source', 'apple_iap');
    expect($response->json('error.conflict.existingSubscription.currentPeriodEndsAt'))->not->toBeNull();
});

it('returns ERR_SUB_001 when the request bundleId does not match Apple JWS', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    config(['subscription.iap.apple.bundle_id' => 'app.zelta']);

    $jws = makeAppleJws([
        'bundleId'              => 'com.evil.app', // mismatched
        'originalTransactionId' => 'apple-bundle-mismatch-001',
    ]);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-bundle-mismatch-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-bundle-mismatch-ffffffffffff',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_SUB_001');
});

it('rejects an Apple JWS whose originalTransactionId does not match request body (ERR_SUB_001)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $jws = makeAppleJws([
        'originalTransactionId' => 'apple-actual-tx',
    ]);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-spoofed-tx', // mismatch
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-tx-mismatch-gggggggggggggggg',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_SUB_001');
});

it('requires Idempotency-Key header (ERR_VALIDATION_001)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(),
        'originalTransactionId' => 'apple-orig-tx-noidem',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION_001');
});

it('appends an event to iap_subscription_events on first verify', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => makeAppleJws(['originalTransactionId' => 'apple-event-test-001']),
        'originalTransactionId' => 'apple-event-test-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-event-store-hhhhhhhhhhhhhhhh',
    ])->assertStatus(200);

    $sub = IapSubscription::query()->first();
    $events = IapSubscriptionEvent::query()->where('aggregate_uuid', $sub->id)->get();

    expect($events)->toHaveCount(1);
    expect($events->first()->event_class)->toBe('AppleSubscriptionVerified');
    expect($events->first()->aggregate_version)->toBe(1);
});

/**
 * Regression: in production with the bundled Apple Root CA G3 pinned, the
 * verifier must reject a JWS that does NOT chain to the pinned root. The
 * forged-receipt fixture here is signed by nothing real — the placeholder
 * signature + missing chain means chain validation fails and we surface
 * ERR_SUB_001 to the user.
 */
it('Apple verifier fails closed in production when JWS chain validation fails', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // Pretend we are in production with the production-default config:
    //   - Bundled Apple Root CA G3 pinned
    //   - APPLE_JWS_VERIFICATION_BYPASS unset (defaults to false)
    $this->app['env'] = 'production';
    config([
        'subscription.iap.apple.root_ca_path'         => storage_path('app/apple/AppleRootCA-G3.cer'),
        'subscription.iap.apple.root_ca_fingerprints' => [
            '63343ABFB89A6A03EBB57E9B3F5FA7BE7C4F5C756F3017B3A8C488C3653E9179',
        ],
        'subscription.iap.apple_jws_verification_bypass' => false,
    ]);

    // makeAppleJws() emits a stub JWS with an empty x5c — chain validation
    // rejects it (alg=ES256 fails because we have <3 certs).
    $jws = makeAppleJws();

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-orig-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-fail-closed-aaaaaaaaaaaaaaaa',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_SUB_001');
    expect(IapSubscription::query()->count())->toBe(0);
    expect(IapReceipt::query()->count())->toBe(0);
});

it('Apple verifier ignores the bypass flag in production and still rejects an unsigned JWS', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // Operator misconfigured prod env: bypass=true should NOT be honoured in
    // production — letting it through is a full auth bypass on the entire
    // Apple IAP surface (any authenticated user could forge a JWS with
    // arbitrary originalTransactionId / productId / expiresDate). The
    // verifier must drop back to real chain validation regardless.
    $this->app['env'] = 'production';
    config(['subscription.iap.apple_jws_verification_bypass' => true]);

    $jws = makeAppleJws();

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-orig-tx-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-bypass-set-bbbbbbbbbbbbbbbb',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_SUB_001');
    expect(IapSubscription::query()->count())->toBe(0);
});

it('Apple verifier still honours the bypass flag in staging (non-production)', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // Staging without provisioned certs is the only legitimate bypass use
    // case. Anything that is NOT 'production' (and NOT 'local'/'testing',
    // which short-circuit even earlier) should accept the flag.
    $this->app['env'] = 'staging';
    config(['subscription.iap.apple_jws_verification_bypass' => true]);

    // The JWS payload's originalTransactionId must match the request body —
    // the verifier rejects drift even when the chain check is bypassed.
    $jws = makeAppleJws(['originalTransactionId' => 'apple-orig-tx-staging-1']);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-orig-tx-staging-1',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-bypass-staging-aaaaaaaaaaaaaa',
    ]);

    $response->assertStatus(200);
    expect(IapSubscription::query()->count())->toBe(1);
});

/**
 * End-to-end happy path: a JWS signed by a properly-chained test root, with
 * the test root's fingerprint pinned. Mirrors the live App Store flow except
 * the cert authority is synthetic — proves the verify endpoint plumbs the
 * verifier correctly and reaches the success branch when chain + signature
 * both validate.
 */
it('Apple verifier accepts a properly-signed JWS in production with pinned test root', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // Build a synthetic 3-cert chain + a JWS signed by the leaf.
    $chain = buildAppleJwsTestChain();

    $jws = signJwsWithChain($chain, [
        'bundleId'              => 'app.zelta',
        'originalTransactionId' => 'apple-prod-happy-001',
        'transactionId'         => 'apple-prod-happy-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
        'price'                 => 499,
        'environment'           => 'Production',
        'inAppOwnershipType'    => 'PURCHASED',
    ]);

    $this->app['env'] = 'production';
    config([
        'subscription.iap.apple.root_ca_path'            => null,
        'subscription.iap.apple.root_ca_fingerprints'    => [$chain['rootFingerprint']],
        'subscription.iap.apple_jws_verification_bypass' => false,
    ]);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $jws,
        'originalTransactionId' => 'apple-prod-happy-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-prod-happy-cccccccccccccccc',
    ]);

    $response->assertStatus(200);
    expect(IapSubscription::query()->where('original_transaction_id', 'apple-prod-happy-001')->count())->toBe(1);
});

it('Apple verifier rejects a tampered JWS in production with pinned test root', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $chain = buildAppleJwsTestChain();
    $jws = signJwsWithChain($chain, [
        'bundleId'              => 'app.zelta',
        'originalTransactionId' => 'apple-prod-tampered-001',
        'transactionId'         => 'apple-prod-tampered-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ]);

    // Tamper with the payload (flip a byte in segment 1). Signature mismatch.
    [$h, $p, $s] = explode('.', $jws);
    $tampered = $h . '.' . substr_replace($p, $p[0] === 'A' ? 'B' : 'A', 0, 1) . '.' . $s;

    $this->app['env'] = 'production';
    config([
        'subscription.iap.apple.root_ca_path'            => null,
        'subscription.iap.apple.root_ca_fingerprints'    => [$chain['rootFingerprint']],
        'subscription.iap.apple_jws_verification_bypass' => false,
    ]);

    $response = $this->postJson('/api/v1/subscription/iap/verify', [
        'platform'              => 'apple_iap',
        'receipt'               => $tampered,
        'originalTransactionId' => 'apple-prod-tampered-001',
        'productId'             => 'zelta_pro_monthly',
        'currency'              => 'EUR',
    ], [
        'Idempotency-Key' => 'idem-prod-tampered-dddddddddddddd',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_SUB_001');
    expect(IapSubscription::query()->count())->toBe(0);
});

/**
 * Build a 3-cert test chain (root → intermediate → leaf, all ES256/P-256).
 *
 * @return array{
 *   rootDer: string,
 *   intermediateDer: string,
 *   leafDer: string,
 *   leafKey: OpenSSLAsymmetricKey,
 *   rootFingerprint: string
 * }
 */
function buildAppleJwsTestChain(): array
{
    $configArgs = [
        'digest_alg'       => 'sha256',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name'       => 'prime256v1',
    ];

    $rootKey = openssl_pkey_new($configArgs);
    if ($rootKey === false) {
        throw new RuntimeException('Test chain: failed to generate root key.');
    }
    $rootCsr = openssl_csr_new(['commonName' => 'Test Apple Root CA G3'], $rootKey, ['digest_alg' => 'sha256']);
    if (! $rootCsr instanceof OpenSSLCertificateSigningRequest) {
        throw new RuntimeException('Test chain: failed to build root CSR.');
    }
    $rootCert = openssl_csr_sign($rootCsr, null, $rootKey, 365, ['digest_alg' => 'sha256']);
    if ($rootCert === false) {
        throw new RuntimeException('Test chain: failed to sign root cert.');
    }

    $intKey = openssl_pkey_new($configArgs);
    if ($intKey === false) {
        throw new RuntimeException('Test chain: failed to generate intermediate key.');
    }
    $intCsr = openssl_csr_new(['commonName' => 'Test Apple WWDR'], $intKey, ['digest_alg' => 'sha256']);
    if (! $intCsr instanceof OpenSSLCertificateSigningRequest) {
        throw new RuntimeException('Test chain: failed to build intermediate CSR.');
    }
    $intCert = openssl_csr_sign($intCsr, $rootCert, $rootKey, 365, ['digest_alg' => 'sha256']);
    if ($intCert === false) {
        throw new RuntimeException('Test chain: failed to sign intermediate cert.');
    }

    $leafKey = openssl_pkey_new($configArgs);
    if ($leafKey === false) {
        throw new RuntimeException('Test chain: failed to generate leaf key.');
    }
    $leafCsr = openssl_csr_new(['commonName' => 'Test Leaf'], $leafKey, ['digest_alg' => 'sha256']);
    if (! $leafCsr instanceof OpenSSLCertificateSigningRequest) {
        throw new RuntimeException('Test chain: failed to build leaf CSR.');
    }
    $leafCert = openssl_csr_sign($leafCsr, $intCert, $intKey, 30, ['digest_alg' => 'sha256']);
    if ($leafCert === false) {
        throw new RuntimeException('Test chain: failed to sign leaf cert.');
    }

    $rootDer = appleTestPemToDer($rootCert);
    $intDer = appleTestPemToDer($intCert);
    $leafDer = appleTestPemToDer($leafCert);

    return [
        'rootDer'         => $rootDer,
        'intermediateDer' => $intDer,
        'leafDer'         => $leafDer,
        'leafKey'         => $leafKey,
        'rootFingerprint' => strtoupper(hash('sha256', $rootDer)),
    ];
}

function appleTestPemToDer(OpenSSLCertificate $cert): string
{
    $pem = '';
    if (! openssl_x509_export($cert, $pem)) {
        throw new RuntimeException('Test helper: openssl_x509_export failed.');
    }
    if (preg_match('/-----BEGIN CERTIFICATE-----(.*?)-----END CERTIFICATE-----/s', $pem, $m) !== 1) {
        throw new RuntimeException('Test helper: PEM did not contain a cert block.');
    }
    $b64 = (string) preg_replace('/\s+/', '', $m[1]);
    $der = base64_decode($b64, true);
    if ($der === false) {
        throw new RuntimeException('Test helper: PEM body could not be decoded.');
    }

    return $der;
}

/**
 * Sign a payload with a test chain and emit the StoreKit-2-shape JWS string.
 *
 * @param array{
 *   rootDer: string,
 *   intermediateDer: string,
 *   leafDer: string,
 *   leafKey: OpenSSLAsymmetricKey,
 *   rootFingerprint: string
 * } $chain
 * @param array<string, mixed> $payload
 */
function signJwsWithChain(array $chain, array $payload): string
{
    $header = [
        'alg' => 'ES256',
        'x5c' => [
            base64_encode($chain['leafDer']),
            base64_encode($chain['intermediateDer']),
            base64_encode($chain['rootDer']),
        ],
    ];

    $headerB64 = rtrim(strtr(base64_encode((string) json_encode($header)), '+/', '-_'), '=');
    $payloadB64 = rtrim(strtr(base64_encode((string) json_encode($payload)), '+/', '-_'), '=');

    $signed = $headerB64 . '.' . $payloadB64;
    $derSig = '';
    openssl_sign($signed, $derSig, $chain['leafKey'], OPENSSL_ALGO_SHA256);

    // Convert DER → raw r||s (mirrors verifier.ecdsaRawToDer).
    $pos = 2;
    if ((ord($derSig[1]) & 0x80) !== 0) {
        $pos = 2 + (ord($derSig[1]) & 0x7F);
    }
    $rLen = ord($derSig[$pos + 1]);
    $r = substr($derSig, $pos + 2, $rLen);
    $pos += 2 + $rLen;
    $sLen = ord($derSig[$pos + 1]);
    $s = substr($derSig, $pos + 2, $sLen);
    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $rawSig = $r . $s;

    $sigB64 = rtrim(strtr(base64_encode($rawSig), '+/', '-_'), '=');

    return $signed . '.' . $sigB64;
}
