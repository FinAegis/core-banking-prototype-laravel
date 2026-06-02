<?php

/**
 * Unit tests for BridgeKycProvider's status + signature paths. The
 * lazy-customer-creation flow + webhook normalization are covered by the
 * full integration tests in tests/Feature/Api/Bridge/*.
 *
 * Pre-§3.1 this file also asserted "deferred" 501 behavior; those tests
 * were removed when the real implementation landed.
 */

declare(strict_types=1);

use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Domain\Compliance\Kyc\Providers\BridgeKycProvider;
use App\Infrastructure\Bridge\BridgeClient;
use App\Infrastructure\Bridge\BridgeWebhookVerifier;
use App\Models\User;

beforeEach(function () {
    config([
        'kyc.providers.bridge.api_key'        => 'sk_test',
        'kyc.providers.bridge.webhook_secret' => 'whsec_test',
    ]);
});

function makeBridgeKycProvider(): BridgeKycProvider
{
    return new BridgeKycProvider(BridgeClient::fromConfig(), BridgeWebhookVerifier::fromConfig());
}

it('reports its name as "bridge"', function () {
    expect(makeBridgeKycProvider()->getName())->toBe('bridge');
});

it('reports X-Webhook-Signature as the webhook signature header', function () {
    expect(makeBridgeKycProvider()->getWebhookSignatureHeader())->toBe('X-Webhook-Signature');
});

it('returns not_started status when no bridge_customers row exists', function () {
    $user = User::factory()->create();

    $result = makeBridgeKycProvider()->getStatus($user->id);

    expect($result['status'])->toBe('not_started');
    expect($result['metadata'])->toBe([]);
});

it('reads kyc_status from bridge_customers row', function () {
    $user = User::factory()->create();
    BridgeCustomer::create([
        'user_id'            => $user->id,
        'bridge_customer_id' => 'cust_test_123',
        'kyc_status'         => BridgeCustomer::KYC_APPROVED,
        'virtual_account_id' => 'va_test_456',
        'supported_rails'    => ['ach', 'sepa'],
        'developer_fee_bps'  => 75,
    ]);

    $result = makeBridgeKycProvider()->getStatus($user->id);

    expect($result['status'])->toBe('approved');
    expect($result['metadata']['bridge_customer_id'])->toBe('cust_test_123');
    expect($result['metadata']['virtual_account_ready'])->toBeTrue();
    expect($result['metadata']['supported_rails'])->toBe(['ach', 'sepa']);
});

it('reports virtual_account_ready false when no virtual_account_id yet', function () {
    $user = User::factory()->create();
    BridgeCustomer::create([
        'user_id'            => $user->id,
        'bridge_customer_id' => 'cust_test_789',
        'kyc_status'         => BridgeCustomer::KYC_PENDING,
        'developer_fee_bps'  => 75,
    ]);

    $result = makeBridgeKycProvider()->getStatus($user->id);

    expect($result['status'])->toBe('pending');
    expect($result['metadata']['virtual_account_ready'])->toBeFalse();
});

it('normalizes customer.kyc_link_completed → approved', function () {
    $user = User::factory()->create();
    BridgeCustomer::create([
        'user_id'            => $user->id,
        'bridge_customer_id' => 'cust_norm_1',
        'kyc_status'         => BridgeCustomer::KYC_PENDING,
        'developer_fee_bps'  => 75,
    ]);

    $normalized = makeBridgeKycProvider()->normalizeWebhookPayload([
        'type' => 'customer.kyc_link_completed',
        'data' => ['object' => ['customer_id' => 'cust_norm_1']],
    ]) ?? throw new RuntimeException('Expected normalized payload, got null');

    expect($normalized['status'])->toBe('approved');
    expect($normalized['event_type'])->toBe('customer.kyc_link_completed');
    expect($normalized['user_id'])->toBe($user->id);
});

it('returns null from normalizeWebhookPayload on unrelated event types (e.g. transfer.*)', function () {
    expect(makeBridgeKycProvider()->normalizeWebhookPayload(['type' => 'transfer.completed']))
        ->toBeNull();
});

it('encrypts virtual_account_details at rest', function () {
    $user = User::factory()->create();
    $details = [
        'iban'           => 'GB29NWBK60161331926819',
        'account_holder' => 'Acme Test',
        'memo'           => 'ZELTA-12345',
    ];

    BridgeCustomer::create([
        'user_id'                 => $user->id,
        'bridge_customer_id'      => 'cust_enc',
        'kyc_status'              => BridgeCustomer::KYC_APPROVED,
        'virtual_account_id'      => 'va_enc',
        'virtual_account_details' => $details,
        'developer_fee_bps'       => 75,
    ]);

    $raw = (string) Illuminate\Support\Facades\DB::table('bridge_customers')
        ->where('user_id', $user->id)
        ->value('virtual_account_details');

    // Encrypted ciphertext should NOT contain the plaintext IBAN
    expect($raw)->not->toContain('GB29NWBK60161331926819');
    expect($raw)->not->toBe((string) json_encode($details));

    // Reading through the model returns the decrypted array
    $customer = BridgeCustomer::where('user_id', $user->id)->firstOrFail();
    expect($customer->virtual_account_details)->toBe($details);
});
