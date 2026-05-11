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
        'subscription_initial'  => 3600,
        'send'                  => 300,
        'swap'                  => 30,
        'ramp_buy'              => 60,
        'ramp_sell'             => 60,
        'card_waitlist_deposit' => 3600,
    ]]);
    config(['pricing.rate_limit' => [
        'quotes_per_minute_per_user' => 60,
        'quotes_per_minute_per_ip'   => 600,
    ]]);
});

/**
 * Helper: create a live PriceQuote row for a user.
 */
function makeLiveQuote(User $user, string $kind = 'subscription_initial'): PriceQuote
{
    $id = (string) Str::uuid();
    $expiresAt = now()->addHour();
    $responsePayload = [
        'quoteId'      => $id,
        'kind'         => $kind,
        'expiresAt'    => $expiresAt->toIso8601String(),
        'feeBreakdown' => [
            ['label' => 'subscription', 'amount' => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR'], 'eurEquivalent' => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR']],
        ],
        'rates'        => [],
        'feeTier'      => ['txFlat' => null, 'swapMarginBps' => 20, 'rampMarginBps' => 100],
        'userOpHash'   => null,
        'termsChanged' => false,
        'saveWithPro'  => null,
    ];

    return PriceQuote::query()->create([
        'id'               => $id,
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => $kind,
        'request_payload'  => ['kind' => $kind, 'currency' => 'EUR'],
        'response_payload' => $responsePayload,
        'entity_key'       => str_repeat('a', 64),
        'user_op_hash'     => null,
        'user_op_payload'  => null,
        'signature'        => str_repeat('b', 64),
        'superseded_by'    => null,
        'terms_changed'    => false,
        'expires_at'       => $expiresAt,
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Happy path
// ──────────────────────────────────────────────────────────────────────────────

it('GET /api/v1/pricing/quote/{quoteId} returns 200 with status=active for a live quote', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $quote = makeLiveQuote($user);

    $response = $this->getJson('/api/v1/pricing/quote/' . $quote->id);

    $response->assertStatus(200);
    $response->assertJsonPath('quoteId', $quote->id);
    $response->assertJsonPath('status', 'active');
});

it('GET /api/v1/pricing/quote/{quoteId} returns status=expired for an expired quote', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $id = (string) Str::uuid();
    PriceQuote::query()->create([
        'id'               => $id,
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => 'subscription_initial',
        'request_payload'  => ['kind' => 'subscription_initial', 'currency' => 'EUR'],
        'response_payload' => ['quoteId' => $id, 'kind' => 'subscription_initial', 'feeBreakdown' => [], 'rates' => [], 'feeTier' => ['txFlat' => null, 'swapMarginBps' => 20, 'rampMarginBps' => 100]],
        'entity_key'       => str_repeat('c', 64),
        'signature'        => str_repeat('d', 64),
        'terms_changed'    => false,
        'expires_at'       => now()->subMinute(),
    ]);

    $response = $this->getJson('/api/v1/pricing/quote/' . $id);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'expired');
});

it('GET /api/v1/pricing/quote/{quoteId} returns status=consumed for a consumed quote', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $quote = makeLiveQuote($user);
    $quote->update(['consumed_at' => now(), 'consumed_by' => 'subscription_id_abc']);

    $response = $this->getJson('/api/v1/pricing/quote/' . $quote->id);

    $response->assertStatus(200);
    $response->assertJsonPath('status', 'consumed');
});

// ──────────────────────────────────────────────────────────────────────────────
// Error paths
// ──────────────────────────────────────────────────────────────────────────────

it('GET /api/v1/pricing/quote/{quoteId} returns ERR_QUO_001 for non-existent quote', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->getJson('/api/v1/pricing/quote/' . Str::uuid());

    $response->assertStatus(404);
    $response->assertJsonPath('error.code', 'ERR_QUO_001');
});

it('GET /api/v1/pricing/quote/{quoteId} returns ERR_QUO_001 (403 behaviour) for quote belonging to another user', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    // Create a quote for $owner.
    $quote = makeLiveQuote($owner);

    // Try to access it as $intruder.
    Sanctum::actingAs($intruder, ['read', 'write', 'delete']);
    $response = $this->getJson('/api/v1/pricing/quote/' . $quote->id);

    // Returns ERR_QUO_001 (404) — does not reveal quote existence to other users.
    $response->assertStatus(404);
    $response->assertJsonPath('error.code', 'ERR_QUO_001');
});

it('GET /api/v1/pricing/quote/{quoteId} returns 401 for unauthenticated request', function (): void {
    $response = $this->getJson('/api/v1/pricing/quote/' . Str::uuid());
    $response->assertStatus(401);
});
