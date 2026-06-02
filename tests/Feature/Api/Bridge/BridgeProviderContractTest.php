<?php

/**
 * BridgeProvider unit-style tests that exercise the RampProviderInterface
 * surface without going through HTTP (the Http::fake calls + DB integration
 * are covered by BridgeKycLinkFlowTest and BridgeWebhookControllerTest).
 *
 * Focus here: quote-synthesis arithmetic, signature header constants,
 * createSession preconditions.
 */

declare(strict_types=1);

use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Domain\Ramp\Providers\BridgeProvider;
use App\Infrastructure\Bridge\BridgeClient;
use App\Infrastructure\Bridge\BridgeWebhookVerifier;
use App\Models\User;

beforeEach(function () {
    config([
        'kyc.providers.bridge.api_key'        => 'sk_test',
        'kyc.providers.bridge.webhook_secret' => 'whsec_test',
    ]);
});

function makeBridgeProvider(): BridgeProvider
{
    return new BridgeProvider(BridgeClient::fromConfig(), BridgeWebhookVerifier::fromConfig());
}

/**
 * Build a complete RampProviderInterface::createSession params array with
 * sane defaults; tests override only the keys they care about.
 *
 * @param  array<string, mixed>  $overrides
 * @return array{type: string, fiat_currency: string, fiat_amount: string, crypto_currency: string, wallet_address: string, quote_id: string|null, user_id?: int, network?: string}
 */
function bridgeSessionParams(array $overrides = []): array
{
    /** @var array{type: string, fiat_currency: string, fiat_amount: string, crypto_currency: string, wallet_address: string, quote_id: string|null, user_id?: int, network?: string} $merged */
    $merged = array_merge([
        'type'            => 'on',
        'fiat_currency'   => 'USD',
        'fiat_amount'     => '100.00',
        'crypto_currency' => 'USDC',
        'wallet_address'  => '0x0000000000000000000000000000000000000001',
        'quote_id'        => null,
    ], $overrides);

    return $merged;
}

it('reports its name as "bridge"', function () {
    expect(makeBridgeProvider()->getName())->toBe('bridge');
});

it('reports X-Webhook-Signature as the webhook signature header', function () {
    expect(makeBridgeProvider()->getWebhookSignatureHeader())->toBe('X-Webhook-Signature');
});

it('reports v1 supported capabilities (USDC, USD/EUR/GBP, buy-only)', function () {
    $supported = makeBridgeProvider()->getSupportedCurrencies();

    expect($supported['fiatCurrencies'])->toBe(['USD', 'EUR', 'GBP']);
    expect($supported['cryptoCurrencies'])->toBe(['USDC']);
    expect($supported['modes'])->toBe(['buy']);  // offramp deferred to v1.1
});

it('synthesizes a quote with 10bps Bridge fee + at-cost Polygon network fee', function () {
    $quotes = makeBridgeProvider()->getQuotes('on', 'USD', '1000.00', 'USDC');

    expect($quotes)->toHaveCount(1);
    $q = $quotes[0];
    expect($q['provider_name'])->toBe('Bridge');
    // 0.10% of 1000 = 1.00
    expect($q['fee'])->toBe(1.0);
    expect($q['network_fee'])->toBe(0.01);
    expect($q['fee_currency'])->toBe('USD');
    expect($q['payment_methods'])->toBe(['ach', 'sepa', 'sepa_instant']);
});

it('throws when fiat amount is not numeric', function () {
    expect(fn () => makeBridgeProvider()->getQuotes('on', 'USD', 'abc', 'USDC'))
        ->toThrow(RuntimeException::class, 'non-negative numeric fiat amount');
});

it('rejects offramp in v1 (createSession with type=off throws)', function () {
    expect(fn () => makeBridgeProvider()->createSession(bridgeSessionParams(['type' => 'off'])))
        ->toThrow(RuntimeException::class, 'deferred to v1.1');
});

it('requires user_id in createSession params', function () {
    expect(fn () => makeBridgeProvider()->createSession(bridgeSessionParams()))
        ->toThrow(RuntimeException::class, 'user_id');
});

it('requires a KYC-approved customer with a virtual account for onramp', function () {
    $user = User::factory()->create();
    // No bridge_customers row at all
    expect(fn () => makeBridgeProvider()->createSession(bridgeSessionParams(['user_id' => $user->id])))
        ->toThrow(RuntimeException::class, 'approved customer with a provisioned virtual account');
});

it('returns deposit instructions for an approved customer onramp session', function () {
    $user = User::factory()->create();
    BridgeCustomer::create([
        'user_id'                 => $user->id,
        'bridge_customer_id'      => 'cust_create_session',
        'kyc_status'              => BridgeCustomer::KYC_APPROVED,
        'virtual_account_id'      => 'va_create_session',
        'virtual_account_details' => [
            'iban'                => 'GB29NWBK60161331926819',
            'account_holder_name' => 'Acme Ramping',
            'memo'                => 'CUSTREF-X9',
        ],
        'supported_rails'   => ['sepa', 'sepa_instant'],
        'developer_fee_bps' => 75,
    ]);

    $result = makeBridgeProvider()->createSession(bridgeSessionParams(['user_id' => $user->id]));
    $deposit = $result['deposit_instructions']
        ?? throw new RuntimeException('Expected deposit_instructions on onramp session');

    expect($result['session_id'])->toBe('bridge_va_va_create_session');
    expect($result['checkout_url'])->toBeNull();
    expect($deposit['iban'])->toBe('GB29NWBK60161331926819');
    expect($deposit['memo'])->toBe('CUSTREF-X9');
    expect($deposit['supportedRails'])->toBe(['sepa', 'sepa_instant']);
    expect($result['metadata']['provider'])->toBe('bridge');
    expect($result['metadata']['network'])->toBe('polygon');
});

it('rejects unsupported networks in v1 (Solana etc.)', function () {
    $user = User::factory()->create();
    BridgeCustomer::create([
        'user_id'                 => $user->id,
        'bridge_customer_id'      => 'cust_net',
        'kyc_status'              => BridgeCustomer::KYC_APPROVED,
        'virtual_account_id'      => 'va_net',
        'virtual_account_details' => ['iban' => 'X'],
        'developer_fee_bps'       => 75,
    ]);

    expect(fn () => makeBridgeProvider()->createSession(bridgeSessionParams([
        'user_id' => $user->id,
        'network' => 'solana',
    ])))->toThrow(RuntimeException::class, "only supports network 'polygon'");
});
