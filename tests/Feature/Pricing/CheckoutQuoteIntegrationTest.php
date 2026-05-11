<?php

declare(strict_types=1);

use App\Domain\Pricing\Models\PriceQuote;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config(['services.pricing.quote_pepper' => 'test-pepper-' . str_repeat('a', 32)]);
});

/**
 * Helper: create a live subscription_initial PriceQuote row.
 */
function makeSubQuote(User $user): PriceQuote
{
    $id = (string) Str::uuid();

    return PriceQuote::query()->create([
        'id'               => $id,
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => 'subscription_initial',
        'request_payload'  => ['kind' => 'subscription_initial', 'currency' => 'EUR'],
        'response_payload' => [
            'quoteId'      => $id,
            'kind'         => 'subscription_initial',
            'feeBreakdown' => [
                ['label' => 'subscription', 'amount' => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR'], 'eurEquivalent' => ['amount' => '499', 'decimals' => 2, 'currency' => 'EUR']],
            ],
            'rates'      => [],
            'feeTier'    => ['txFlat' => null, 'swapMarginBps' => 20, 'rampMarginBps' => 100],
            'userOpHash' => null,
        ],
        'entity_key'    => hash('sha256', $id . 'subscription'),
        'signature'     => str_repeat('c', 64),
        'terms_changed' => false,
        'expires_at'    => now()->addHour(),
    ]);
}

it('POST /subscription/checkout with valid quoteId redeems the quote', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $quote = makeSubQuote($user);
    expect($quote->isLive())->toBeTrue();

    // Mock the Stripe session creation — we just need the service layer to call redeem().
    // Since the Stripe call will fail without credentials, we expect it to throw AFTER
    // the quote is consumed. The test verifies consumed_at was set.
    // For testing purposes, we check that the request reached the quote-redemption step.
    $this->postJson('/api/v1/subscription/checkout', [
        'plan'              => 'monthly_pro',
        'quoteId'           => $quote->id,
        'withdrawalConsent' => [
            'given'       => true,
            'shownAt'     => now()->toIso8601String(),
            'acceptedAt'  => now()->toIso8601String(),
            'consentText' => 'I waive my 14-day right of withdrawal.',
            'version'     => 1,
        ],
    ], ['Idempotency-Key' => Str::random(24)]);

    // Regardless of the Stripe result, the quote should now be consumed
    // (it gets redeemed before the Stripe call).
    $quote->refresh();
    expect($quote->consumed_at)->not->toBeNull();
    expect($quote->consumed_by)->toBe('subscription_checkout');
});

it('POST /subscription/checkout without quoteId continues to work (back-compat)', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // No quoteId — should not fail with a quote-related error.
    $response = $this->postJson('/api/v1/subscription/checkout', [
        'plan'              => 'monthly_pro',
        'withdrawalConsent' => [
            'given'       => true,
            'shownAt'     => now()->toIso8601String(),
            'acceptedAt'  => now()->toIso8601String(),
            'consentText' => 'I waive my 14-day right of withdrawal.',
            'version'     => 1,
        ],
    ], ['Idempotency-Key' => Str::random(24)]);

    // If Stripe isn't configured, it throws a Throwable and re-throws.
    // We just want to confirm it's not a quote error code.
    $code = $response->json('error.code') ?? '';
    expect($code)->not->toStartWith('ERR_QUO');
    expect($code)->not->toStartWith('ERR_QUOTE');
});

it('POST /subscription/checkout with expired quoteId returns ERR_QUOTE_001', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $id = (string) Str::uuid();
    PriceQuote::query()->create([
        'id'               => $id,
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => 'subscription_initial',
        'request_payload'  => [],
        'response_payload' => [],
        'entity_key'       => str_repeat('d', 64),
        'signature'        => str_repeat('e', 64),
        'terms_changed'    => false,
        'expires_at'       => now()->subMinute(), // Already expired.
    ]);

    $response = $this->postJson('/api/v1/subscription/checkout', [
        'plan'              => 'monthly_pro',
        'quoteId'           => $id,
        'withdrawalConsent' => [
            'given'       => true,
            'shownAt'     => now()->toIso8601String(),
            'acceptedAt'  => now()->toIso8601String(),
            'consentText' => 'I waive my 14-day right of withdrawal.',
            'version'     => 1,
        ],
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(410);
    $response->assertJsonPath('error.code', 'ERR_QUOTE_001');
});

it('POST /subscription/checkout with already-consumed quoteId returns ERR_QUO_002', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $quote = makeSubQuote($user);
    $quote->update(['consumed_at' => now(), 'consumed_by' => 'prior_checkout']);

    $response = $this->postJson('/api/v1/subscription/checkout', [
        'plan'              => 'monthly_pro',
        'quoteId'           => $quote->id,
        'withdrawalConsent' => [
            'given'       => true,
            'shownAt'     => now()->toIso8601String(),
            'acceptedAt'  => now()->toIso8601String(),
            'consentText' => 'I waive my 14-day right of withdrawal.',
            'version'     => 1,
        ],
    ], ['Idempotency-Key' => Str::random(24)]);

    $response->assertStatus(409);
    $response->assertJsonPath('error.code', 'ERR_QUO_002');
});
