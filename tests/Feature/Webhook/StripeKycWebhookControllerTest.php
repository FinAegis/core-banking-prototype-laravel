<?php

declare(strict_types=1);

use App\Domain\TrustCert\Models\VerificationPayment;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    config([
        'cache.default'                      => 'array',
        'services.stripe.kyc_webhook_secret' => 'test_secret',
    ]);

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
 * @param array<string, mixed> $payload
 *
 * @return array<string, string>
 */
function stripeKycSignedHeaders(array $payload): array
{
    $body = (string) json_encode($payload);
    $timestamp = time();
    $secret = (string) config('services.stripe.kyc_webhook_secret');
    $signature = hash_hmac('sha256', $timestamp . '.' . $body, $secret);

    return ['Stripe-Signature' => "t={$timestamp},v1={$signature}"];
}

function seedTrustCertApplication(int $userId, string $applicationId, string $status = 'paid'): void
{
    Cache::put("trustcert_application:{$userId}", [
        'id'           => $applicationId,
        'user_id'      => $userId,
        'target_level' => 'basic',
        'status'       => $status,
        'created_at'   => now()->toIso8601String(),
        'updated_at'   => now()->toIso8601String(),
    ], now()->addDays(30));
}

it('reverts application to payment_required on checkout.session.expired', function (): void {
    seedTrustCertApplication(7, 'app_xyz', 'paid'); // simulate optimistic state

    $payload = [
        'type' => 'checkout.session.expired',
        'data' => ['object' => [
            'id'       => 'cs_test_123',
            'metadata' => ['application_id' => 'app_xyz', 'user_id' => '7'],
        ]],
    ];
    $response = $this->postJson('/api/webhooks/stripe/kyc', $payload, stripeKycSignedHeaders($payload));

    $response->assertStatus(200)->assertJson(['received' => true]);

    $app = Cache::get('trustcert_application:7');
    expect($app['status'])->toBe('payment_required')
        ->and($app['last_payment_failure_reason'])->toBe('checkout.session.expired')
        ->and($app['last_payment_session_id'])->toBe('cs_test_123');
});

it('reverts application to payment_required on checkout.session.async_payment_failed', function (): void {
    seedTrustCertApplication(7, 'app_xyz', 'paid');

    $payload = [
        'type' => 'checkout.session.async_payment_failed',
        'data' => ['object' => [
            'id'       => 'cs_test_456',
            'metadata' => ['application_id' => 'app_xyz', 'user_id' => '7'],
        ]],
    ];
    $response = $this->postJson('/api/webhooks/stripe/kyc', $payload, stripeKycSignedHeaders($payload));

    $response->assertStatus(200);

    $app = Cache::get('trustcert_application:7');
    expect($app['status'])->toBe('payment_required')
        ->and($app['last_payment_failure_reason'])->toBe('checkout.session.async_payment_failed');
});

it('does not unwind a session that already completed', function (): void {
    seedTrustCertApplication(7, 'app_xyz', 'paid');

    VerificationPayment::create([
        'user_id'           => 7,
        'application_id'    => 'app_xyz',
        'method'            => 'card',
        'amount'            => '4.99',
        'currency'          => 'USD',
        'status'            => 'completed',
        'stripe_session_id' => 'cs_test_789',
    ]);

    $payload = [
        'type' => 'checkout.session.expired',
        'data' => ['object' => [
            'id'       => 'cs_test_789',
            'metadata' => ['application_id' => 'app_xyz', 'user_id' => '7'],
        ]],
    ];
    $response = $this->postJson('/api/webhooks/stripe/kyc', $payload, stripeKycSignedHeaders($payload));

    $response->assertStatus(200);

    $app = Cache::get('trustcert_application:7');
    expect($app['status'])->toBe('paid'); // unchanged
});

it('marks application refunded on charge.refunded', function (): void {
    seedTrustCertApplication(7, 'app_xyz', 'paid');

    VerificationPayment::create([
        'user_id'           => 7,
        'application_id'    => 'app_xyz',
        'method'            => 'card',
        'amount'            => '4.99',
        'currency'          => 'USD',
        'status'            => 'completed',
        'stripe_session_id' => 'cs_test_999',
    ]);

    $payload = [
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id'              => 'ch_test_1',
            'amount_refunded' => 499,
            'currency'        => 'usd',
            'metadata'        => ['checkout_session_id' => 'cs_test_999'],
        ]],
    ];
    $response = $this->postJson('/api/webhooks/stripe/kyc', $payload, stripeKycSignedHeaders($payload));

    $response->assertStatus(200);

    $app = Cache::get('trustcert_application:7');
    expect($app['status'])->toBe('refunded')
        ->and($app['refund_amount'])->toBe('4.99')
        ->and($app['refund_currency'])->toBe('USD');

    $payment = VerificationPayment::where('stripe_session_id', 'cs_test_999')->first();
    expect($payment?->status)->toBe('refunded');
});

it('handles charge.refunded idempotently when already refunded', function (): void {
    seedTrustCertApplication(7, 'app_xyz', 'refunded');

    VerificationPayment::create([
        'user_id'           => 7,
        'application_id'    => 'app_xyz',
        'method'            => 'card',
        'amount'            => '4.99',
        'currency'          => 'USD',
        'status'            => 'refunded',
        'stripe_session_id' => 'cs_test_already',
    ]);

    $payload = [
        'type' => 'charge.refunded',
        'data' => ['object' => [
            'id'              => 'ch_2',
            'amount_refunded' => 499,
            'currency'        => 'usd',
            'metadata'        => ['checkout_session_id' => 'cs_test_already'],
        ]],
    ];
    $response = $this->postJson('/api/webhooks/stripe/kyc', $payload, stripeKycSignedHeaders($payload));

    $response->assertStatus(200);
    // No exception, no double mutation.
});

it('rejects unsigned webhooks with 401', function (): void {
    $response = $this->postJson('/api/webhooks/stripe/kyc', [
        'type' => 'checkout.session.expired',
        'data' => ['object' => ['id' => 'cs_x', 'metadata' => []]],
    ]);

    $response->assertStatus(401);
});
