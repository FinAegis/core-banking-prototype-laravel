<?php

declare(strict_types=1);

use App\Domain\Pricing\Models\PriceQuote;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config(['services.pricing.quote_pepper' => 'test-pepper-' . str_repeat('a', 32)]);
    config(['pricing.tiers' => [
        'free' => [
            'tx_flat_eur_cents'    => 20,
            'tx_flat_asset_amount' => '1000000',
            'swap_margin_bps'      => 20,
            'ramp_margin_bps'      => 100,
        ],
        'pro' => [
            'tx_flat_eur_cents'    => 5,
            'tx_flat_asset_amount' => '50000',
            'swap_margin_bps'      => 5,
            'ramp_margin_bps'      => 50,
        ],
    ]]);
    config(['pricing.quote_ttl_seconds' => [
        'send'                  => 300,
        'swap'                  => 30,
        'ramp_buy'              => 60,
        'ramp_sell'             => 60,
        'subscription_initial'  => 3600,
        'card_waitlist_deposit' => 3600,
    ]]);
    config(['pricing.rate_limit' => [
        'quotes_per_minute_per_user' => 60,
        'quotes_per_minute_per_ip'   => 600,
    ]]);
});

// ──────────────────────────────────────────────────────────────────────────────
// Happy paths
// ──────────────────────────────────────────────────────────────────────────────

it('POST /api/v1/pricing/quote returns 200 for kind=send with USDC', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'      => 'send',
        'amount'    => ['amount' => '1000000', 'decimals' => 6, 'asset' => 'USDC'],
        'from'      => ['asset' => 'USDC', 'network' => 'polygon'],
        'to'        => ['asset' => 'USDC', 'network' => 'base'],
        'recipient' => '0xabcdef1234567890abcdef1234567890abcdef12',
        'currency'  => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'quoteId',
        'kind',
        'expiresAt',
        'feeBreakdown',
        'rates',
        'feeTier',
        'userOpHash',
        'termsChanged',
        'saveWithPro',
    ]);
    expect($response->json('kind'))->toBe('send');
    expect($response->json('termsChanged'))->toBeFalse();
});

it('POST /api/v1/pricing/quote returns 200 for kind=subscription_initial', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'     => 'subscription_initial',
        'amount'   => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR'],
        'currency' => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(200);
    expect($response->json('kind'))->toBe('subscription_initial');
    expect($response->json('quoteId'))->not->toBeNull();
    expect($response->json('expiresAt'))->not->toBeNull();
    expect($response->json('rates'))->toBe([]);
    expect($response->json('userOpHash'))->toBeNull();
});

it('POST /api/v1/pricing/quote returns 200 for kind=card_waitlist_deposit', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'     => 'card_waitlist_deposit',
        'amount'   => ['amount' => '500', 'decimals' => 2, 'currency' => 'EUR'],
        'currency' => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(200);
    expect($response->json('kind'))->toBe('card_waitlist_deposit');
});

it('POST /api/v1/pricing/quote saveWithPro is null for subscription_initial kind', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'     => 'subscription_initial',
        'amount'   => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR'],
        'currency' => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(200);
    expect($response->json('saveWithPro'))->toBeNull();
});

it('POST /api/v1/pricing/quote returns no service fee for native ETH send', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'      => 'send',
        'amount'    => ['amount' => '1000000000000000000', 'decimals' => 18, 'asset' => 'ETH'],
        'from'      => ['asset' => 'ETH', 'network' => 'ethereum'],
        'to'        => ['asset' => 'ETH', 'network' => 'ethereum'],
        'recipient' => '0xabcdef1234567890abcdef1234567890abcdef12',
        'currency'  => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(200);

    // feeBreakdown must NOT contain a 'service' label for native-asset sends.
    $labels = array_column($response->json('feeBreakdown'), 'label');
    expect($labels)->not->toContain('service');
    expect($response->json('saveWithPro'))->toBeNull();
});

it('dry-run returns quoteId null and does not persist a row', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // dryRun is a query parameter — add as part of the URL, not the body.
    $response = $this->postJson('/api/v1/pricing/quote?dryRun=true', [
        'kind'      => 'send',
        'amount'    => ['amount' => '1000000', 'decimals' => 6, 'asset' => 'USDC'],
        'from'      => ['asset' => 'USDC', 'network' => 'polygon'],
        'to'        => ['asset' => 'USDC', 'network' => 'base'],
        'recipient' => '0xabcdef1234567890abcdef1234567890abcdef12',
        'currency'  => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(200);
    expect($response->json('quoteId'))->toBeNull();
    expect($response->json('expiresAt'))->toBeNull();
    expect(PriceQuote::query()->count())->toBe(0);
});

// ──────────────────────────────────────────────────────────────────────────────
// Entity-key dedup (Q2.1)
// ──────────────────────────────────────────────────────────────────────────────

it('same intent within TTL returns same quoteId (entity-key dedup)', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $payload = [
        'kind'     => 'subscription_initial',
        'amount'   => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR'],
        'currency' => 'EUR',
    ];

    $r1 = $this->postJson('/api/v1/pricing/quote', $payload, ['Idempotency-Key' => Str::random(24)]);
    $r2 = $this->postJson('/api/v1/pricing/quote', $payload, ['Idempotency-Key' => Str::random(24)]);

    $r1->assertStatus(200);
    $r2->assertStatus(200);
    expect($r1->json('quoteId'))->toBe($r2->json('quoteId'));
    expect(PriceQuote::query()->count())->toBe(1);
});

// ──────────────────────────────────────────────────────────────────────────────
// Error paths
// ──────────────────────────────────────────────────────────────────────────────

it('returns ERR_VALIDATION_001 when Idempotency-Key header is missing', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'     => 'subscription_initial',
        'amount'   => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR'],
        'currency' => 'EUR',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION_001');
});

it('returns ERR_VALIDATION_002 when amount contains a decimal point', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'     => 'subscription_initial',
        'amount'   => ['amount' => '4.99', 'decimals' => 2, 'currency' => 'EUR'],
        'currency' => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(422);
    // The error body contains ERR_VALIDATION_002 from MoneyFormRule in the errors.amount messages.
    $body = $response->json();
    $amountErrors = implode(' ', (array) ($body['errors']['amount'] ?? []));
    expect($amountErrors)->toContain('ERR_VALIDATION_002');
});

it('returns ERR_CUR_001 when currency is not EUR', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'     => 'subscription_initial',
        'amount'   => ['amount' => '499', 'decimals' => 2, 'currency' => 'USD'],
        'currency' => 'USD',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(400);
    $response->assertJsonPath('error.code', 'ERR_CUR_001');
});

it('returns 401 for unauthenticated request', function (): void {
    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'     => 'subscription_initial',
        'amount'   => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR'],
        'currency' => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(401);
});

it('returns ERR_QUO_005 on rate-limit breach (61st request within 1 minute)', function (): void {
    config(['pricing.rate_limit' => [
        'quotes_per_minute_per_user' => 3,
        'quotes_per_minute_per_ip'   => 600,
    ]]);

    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // First 3 requests succeed (may return 422 from validation but not 429).
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/pricing/quote', [
            'kind'     => 'subscription_initial',
            'amount'   => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR'],
            'currency' => 'EUR',
        ], ['Idempotency-Key' => Str::random(24)]);
    }

    // 4th request should be rate-limited.
    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'     => 'subscription_initial',
        'amount'   => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR'],
        'currency' => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(429);
    $response->assertJsonPath('error.code', 'ERR_QUO_005');
});

// ──────────────────────────────────────────────────────────────────────────────
// Money response shape
// ──────────────────────────────────────────────────────────────────────────────

it('all Money fields in the response use the smallest-unit triple format', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/pricing/quote', [
        'kind'      => 'send',
        'amount'    => ['amount' => '1000000', 'decimals' => 6, 'asset' => 'USDC'],
        'from'      => ['asset' => 'USDC', 'network' => 'polygon'],
        'to'        => ['asset' => 'USDC', 'network' => 'base'],
        'recipient' => '0xabcdef1234567890abcdef1234567890abcdef12',
        'currency'  => 'EUR',
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(200);

    $feeBreakdown = $response->json('feeBreakdown');
    foreach ($feeBreakdown as $item) {
        expect($item['amount'])->toHaveKeys(['amount', 'decimals']);
        expect($item['eurEquivalent'])->toHaveKeys(['amount', 'decimals']);
        // Must not be a decimal string.
        expect($item['amount']['amount'])->toMatch('/^[0-9]+$/');
        expect($item['eurEquivalent']['amount'])->toMatch('/^[0-9]+$/');
        // Must have exactly one of currency or asset.
        $hasCurrency = isset($item['amount']['currency']);
        $hasAsset = isset($item['amount']['asset']);
        expect($hasCurrency xor $hasAsset)->toBeTrue();
    }
});
