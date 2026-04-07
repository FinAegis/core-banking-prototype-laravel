<?php

declare(strict_types=1);

use App\Domain\TrustCert\Models\VerificationPayment;
use App\Http\Controllers\Api\V1\TrustCertPaymentController;
use App\Http\Controllers\Api\Webhook\StripeKycWebhookController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);

    // Recreate table without FK to avoid users table dependency in tests
    Schema::dropIfExists('verification_payments');
    Schema::create('verification_payments', function ($table): void {
        $table->uuid('id')->primary();
        $table->unsignedBigInteger('user_id')->index();
        $table->string('application_id', 128);
        $table->string('method', 20);
        $table->decimal('amount', 10, 2);
        $table->string('currency', 3)->default('USD');
        $table->string('status', 20)->default('completed');
        $table->string('stripe_session_id', 255)->nullable();
        $table->string('iap_transaction_id', 255)->nullable();
        $table->string('platform', 10)->nullable();
        $table->timestamps();

        $table->unique('application_id');
    });
});

/**
 * @param array<string, mixed> $data
 */
function makePaymentRequest(string $uri, string $method = 'POST', array $data = [], int $userId = 1): Request
{
    $request = Request::create($uri, $method, $data, [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($data));
    $user = Mockery::mock(User::class)->makePartial();
    $user->id = $userId;
    $user->email = 'test@example.com';
    $request->setUserResolver(fn () => $user);

    return $request;
}

function storeTestApplication(int $userId, string $applicationId, string $targetLevel = 'basic', string $status = 'pending'): void
{
    Cache::put("trustcert_application:{$userId}", [
        'id'           => $applicationId,
        'user_id'      => $userId,
        'target_level' => $targetLevel,
        'status'       => $status,
        'created_at'   => now()->toIso8601String(),
        'updated_at'   => now()->toIso8601String(),
    ], now()->addDays(30));
}

// ---------- Wallet payment tests ----------

describe('TrustCertPaymentController payWallet', function (): void {
    it('pays successfully with sufficient wallet balance', function (): void {
        $applicationId = 'app_test123';
        storeTestApplication(1, $applicationId, 'basic');
        Cache::put('wallet_balance:1', '100.00');

        $controller = new TrustCertPaymentController();
        $response = $controller->payWallet($applicationId, makePaymentRequest('/pay'));
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(200)
            ->and($data)->toHaveKeys(['receiptId', 'amount', 'currency', 'paidAt'])
            ->and($data['amount'])->toBe(4.99)
            ->and($data['currency'])->toBe('USD')
            ->and($data['receiptId'])->toStartWith('rcpt_');

        // Verify payment was recorded
        expect(VerificationPayment::where('application_id', $applicationId)->exists())->toBeTrue();

        // Verify application status was updated in cache
        $app = Cache::get('trustcert_application:1');
        expect($app['status'])->toBe('paid');
    });

    it('returns 402 with insufficient balance', function (): void {
        $applicationId = 'app_poor';
        storeTestApplication(1, $applicationId, 'basic');
        Cache::put('wallet_balance:1', '2.15');

        $controller = new TrustCertPaymentController();
        $response = $controller->payWallet($applicationId, makePaymentRequest('/pay'));
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(402)
            ->and($data['error'])->toBe('ERR_CERT_501')
            ->and($data['required'])->toBe(4.99)
            ->and($data['available'])->toBe(2.15);
    });

    it('returns 404 for non-existent application', function (): void {
        $controller = new TrustCertPaymentController();
        $response = $controller->payWallet('app_nonexistent', makePaymentRequest('/pay'));

        expect($response->getStatusCode())->toBe(404)
            ->and($response->getData(true)['error'])->toBe('ERR_CERT_404');
    });

    it('returns 409 when already paid', function (): void {
        $applicationId = 'app_already_paid';
        storeTestApplication(1, $applicationId, 'basic');
        Cache::put('wallet_balance:1', '100.00');

        // Record existing payment
        VerificationPayment::create([
            'user_id'        => 1,
            'application_id' => $applicationId,
            'method'         => 'wallet',
            'amount'         => '4.99',
            'currency'       => 'USD',
            'status'         => 'completed',
        ]);

        $controller = new TrustCertPaymentController();
        $response = $controller->payWallet($applicationId, makePaymentRequest('/pay'));

        expect($response->getStatusCode())->toBe(409)
            ->and($response->getData(true)['error'])->toBe('ERR_CERT_409');
    });

    it('charges correct fee for level 3 (high)', function (): void {
        $applicationId = 'app_high_level';
        storeTestApplication(1, $applicationId, 'high');
        Cache::put('wallet_balance:1', '100.00');

        $controller = new TrustCertPaymentController();
        $response = $controller->payWallet($applicationId, makePaymentRequest('/pay'));
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(200)
            ->and($data['amount'])->toBe(9.99);
    });
});

// ---------- Card payment tests ----------

describe('TrustCertPaymentController payCard', function (): void {
    it('returns 404 for non-existent application', function (): void {
        $controller = new TrustCertPaymentController();
        $response = $controller->payCard('app_nonexistent', makePaymentRequest('/pay/card'));

        expect($response->getStatusCode())->toBe(404);
    });

    it('returns 409 when already paid', function (): void {
        $applicationId = 'app_card_paid';
        storeTestApplication(1, $applicationId, 'basic');

        VerificationPayment::create([
            'user_id'        => 1,
            'application_id' => $applicationId,
            'method'         => 'card',
            'amount'         => '4.99',
            'currency'       => 'USD',
            'status'         => 'completed',
        ]);

        $controller = new TrustCertPaymentController();
        $response = $controller->payCard($applicationId, makePaymentRequest('/pay/card'));

        expect($response->getStatusCode())->toBe(409);
    });

    it('creates stripe checkout session successfully', function (): void {
        $applicationId = 'app_card_ok';
        storeTestApplication(1, $applicationId, 'basic');

        config(['services.stripe.secret' => 'sk_test_fake']);

        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response([
                'id'         => 'cs_test_session123',
                'url'        => 'https://checkout.stripe.com/pay/cs_test_session123',
                'expires_at' => now()->addMinutes(30)->timestamp,
            ], 200),
        ]);

        $controller = new TrustCertPaymentController();
        $response = $controller->payCard($applicationId, makePaymentRequest('/pay/card'));
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(200)
            ->and($data)->toHaveKeys(['sessionId', 'checkoutUrl', 'expiresAt'])
            ->and($data['sessionId'])->toBe('cs_test_session123')
            ->and($data['checkoutUrl'])->toContain('checkout.stripe.com');
    });

    it('returns 503 when stripe secret not configured', function (): void {
        $applicationId = 'app_no_stripe';
        storeTestApplication(1, $applicationId, 'basic');

        config(['services.stripe.secret' => '']);

        $controller = new TrustCertPaymentController();
        $response = $controller->payCard($applicationId, makePaymentRequest('/pay/card'));

        expect($response->getStatusCode())->toBe(503);
    });
});

// ---------- IAP payment tests ----------

describe('TrustCertPaymentController payIap', function (): void {
    it('returns 404 for non-existent application', function (): void {
        $controller = new TrustCertPaymentController();
        $request = makePaymentRequest('/pay/iap', 'POST', [
            'receipt'  => base64_encode('test_receipt'),
            'platform' => 'ios',
        ]);

        $response = $controller->payIap('app_nonexistent', $request);

        expect($response->getStatusCode())->toBe(404);
    });

    it('returns 409 when already paid', function (): void {
        $applicationId = 'app_iap_paid';
        storeTestApplication(1, $applicationId, 'basic');

        VerificationPayment::create([
            'user_id'        => 1,
            'application_id' => $applicationId,
            'method'         => 'iap',
            'amount'         => '4.99',
            'currency'       => 'USD',
            'status'         => 'completed',
        ]);

        $controller = new TrustCertPaymentController();
        $request = makePaymentRequest('/pay/iap', 'POST', [
            'receipt'  => base64_encode('test_receipt'),
            'platform' => 'ios',
        ]);
        $response = $controller->payIap($applicationId, $request);

        expect($response->getStatusCode())->toBe(409);
    });

    it('pays successfully with valid IAP receipt', function (): void {
        $applicationId = 'app_iap_ok';
        storeTestApplication(1, $applicationId, 'verified');

        $controller = new TrustCertPaymentController();
        $request = makePaymentRequest('/pay/iap', 'POST', [
            'receipt'  => base64_encode('valid_receipt_data'),
            'platform' => 'android',
        ]);
        $response = $controller->payIap($applicationId, $request);
        $data = $response->getData(true);

        expect($response->getStatusCode())->toBe(200)
            ->and($data)->toHaveKeys(['receiptId', 'amount', 'currency', 'paidAt'])
            ->and($data['receiptId'])->toStartWith('rcpt_iap_')
            ->and($data['amount'])->toBe(4.99);

        // Verify payment stored
        $payment = VerificationPayment::where('application_id', $applicationId)->first();
        expect($payment)->not->toBeNull();
        if ($payment !== null) {
            expect($payment->method)->toBe('iap')
                ->and($payment->platform)->toBe('android');
        }
    });
});

// ---------- Stripe KYC webhook tests ----------

describe('StripeKycWebhookController', function (): void {
    it('processes checkout.session.completed and marks application as paid', function (): void {
        $applicationId = 'app_stripe_hook';
        storeTestApplication(42, $applicationId, 'basic');

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'           => 'cs_live_abc123',
                    'amount_total' => 499,
                    'metadata'     => [
                        'application_id' => $applicationId,
                        'user_id'        => '42',
                        'level'          => 'basic',
                    ],
                ],
            ],
        ];

        $request = Request::create('/webhooks/stripe/kyc', 'POST', $payload, [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($payload));

        $controller = new StripeKycWebhookController();
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200)
            ->and($response->getData(true)['received'])->toBeTrue();

        // Verify payment was recorded
        $payment = VerificationPayment::where('application_id', $applicationId)->first();
        expect($payment)->not->toBeNull();
        if ($payment !== null) {
            expect($payment->method)->toBe('card')
                ->and($payment->stripe_session_id)->toBe('cs_live_abc123')
                ->and((string) $payment->amount)->toBe('4.99');
        }

        // Verify application marked as paid
        $app = Cache::get('trustcert_application:42');
        expect($app['status'])->toBe('paid')
            ->and($app['payment_method'])->toBe('card');
    });

    it('handles idempotent duplicate webhook delivery', function (): void {
        $applicationId = 'app_idempotent';
        storeTestApplication(42, $applicationId, 'basic');

        // Pre-record a payment
        VerificationPayment::create([
            'user_id'           => 42,
            'application_id'    => $applicationId,
            'method'            => 'card',
            'amount'            => '4.99',
            'currency'          => 'USD',
            'status'            => 'completed',
            'stripe_session_id' => 'cs_live_dup',
        ]);

        $payload = [
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id'           => 'cs_live_dup',
                    'amount_total' => 499,
                    'metadata'     => [
                        'application_id' => $applicationId,
                        'user_id'        => '42',
                        'level'          => 'basic',
                    ],
                ],
            ],
        ];

        $request = Request::create('/webhooks/stripe/kyc', 'POST', $payload, [], [], ['CONTENT_TYPE' => 'application/json'], (string) json_encode($payload));

        $controller = new StripeKycWebhookController();
        $response = $controller->handle($request);

        expect($response->getStatusCode())->toBe(200);

        // Should still only have one payment
        expect(VerificationPayment::where('application_id', $applicationId)->count())->toBe(1);
    });
});

// ---------- Fee schedule tests ----------

describe('TrustCertPaymentController fee schedule', function (): void {
    it('returns correct fees for each level', function (): void {
        expect(TrustCertPaymentController::getVerificationFee(1))->toBe('4.99')
            ->and(TrustCertPaymentController::getVerificationFee(2))->toBe('4.99')
            ->and(TrustCertPaymentController::getVerificationFee(3))->toBe('9.99')
            ->and(TrustCertPaymentController::getVerificationFee(4))->toBe('9.99')
            ->and(TrustCertPaymentController::getVerificationFee(0))->toBeNull();
    });
});

// ---------- Route existence tests ----------

describe('TrustCert payment routes', function (): void {
    it('has wallet payment route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.trustcert.applications.pay.wallet');
        expect($route)->not->toBeNull();
    });

    it('has card payment route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.trustcert.applications.pay.card');
        expect($route)->not->toBeNull();
    });

    it('has IAP payment route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('mobile.trustcert.applications.pay.iap');
        expect($route)->not->toBeNull();
    });

    it('has stripe KYC webhook route defined', function (): void {
        $route = app('router')->getRoutes()->getByName('api.webhooks.stripe.kyc');
        expect($route)->not->toBeNull();
    });
});
