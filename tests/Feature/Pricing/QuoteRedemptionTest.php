<?php

declare(strict_types=1);

use App\Domain\Pricing\Exceptions\QuoteRedemptionException;
use App\Domain\Pricing\Models\PriceQuote;
use App\Domain\Pricing\Services\QuoteService;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config(['services.pricing.quote_pepper' => 'test-pepper-' . str_repeat('a', 32)]);
});

/**
 * Helper: create a live PriceQuote row for redemption tests.
 */
function makeLiveRedeemableQuote(User $user, string $kind = 'subscription_initial', ?string $userOpHash = null): PriceQuote
{
    $id = (string) Str::uuid();

    return PriceQuote::query()->create([
        'id'               => $id,
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => $kind,
        'request_payload'  => ['kind' => $kind, 'currency' => 'EUR'],
        'response_payload' => ['quoteId' => $id, 'kind' => $kind, 'feeBreakdown' => [], 'rates' => [], 'feeTier' => ['txFlat' => null, 'swapMarginBps' => 20, 'rampMarginBps' => 100]],
        'entity_key'       => hash('sha256', $id),
        'user_op_hash'     => $userOpHash,
        'signature'        => str_repeat('b', 64),
        'terms_changed'    => false,
        'expires_at'       => now()->addHour(),
    ]);
}

// ──────────────────────────────────────────────────────────────────────────────
// Happy redemption
// ──────────────────────────────────────────────────────────────────────────────

it('QuoteService::redeem() marks consumed_at and consumed_by on success', function (): void {
    $user = User::factory()->create();
    $quote = makeLiveRedeemableQuote($user);

    /** @var QuoteService $service */
    $service = app(QuoteService::class);
    $service->redeem($quote->id, $user, 'subscription_checkout');

    $quote->refresh();
    expect($quote->consumed_at)->not->toBeNull();
    expect($quote->consumed_by)->toBe('subscription_checkout');
});

// ──────────────────────────────────────────────────────────────────────────────
// ERR_QUO_001 — not found / wrong user
// ──────────────────────────────────────────────────────────────────────────────

it('redeem() throws ERR_QUO_001 when quote does not exist', function (): void {
    $user = User::factory()->create();

    /** @var QuoteService $service */
    $service = app(QuoteService::class);

    expect(fn () => $service->redeem((string) Str::uuid(), $user, 'test'))
        ->toThrow(QuoteRedemptionException::class);

    try {
        $service->redeem((string) Str::uuid(), $user, 'test');
    } catch (QuoteRedemptionException $e) {
        expect($e->errorCode)->toBe('ERR_QUO_001');
    }
});

it('redeem() throws ERR_QUO_001 when quote belongs to another user', function (): void {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $quote = makeLiveRedeemableQuote($owner);

    /** @var QuoteService $service */
    $service = app(QuoteService::class);

    try {
        $service->redeem($quote->id, $intruder, 'test');
        expect(true)->toBeFalse('Expected exception not thrown');
    } catch (QuoteRedemptionException $e) {
        expect($e->errorCode)->toBe('ERR_QUO_001');
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// ERR_QUO_002 — already consumed
// ──────────────────────────────────────────────────────────────────────────────

it('redeem() throws ERR_QUO_002 on consumed-quote replay', function (): void {
    $user = User::factory()->create();
    $quote = makeLiveRedeemableQuote($user);

    // Consume once.
    $quote->update(['consumed_at' => now(), 'consumed_by' => 'first_call']);

    /** @var QuoteService $service */
    $service = app(QuoteService::class);

    try {
        $service->redeem($quote->id, $user, 'second_call');
        expect(true)->toBeFalse('Expected exception not thrown');
    } catch (QuoteRedemptionException $e) {
        expect($e->errorCode)->toBe('ERR_QUO_002');
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// ERR_QUOTE_001 — expired
// ──────────────────────────────────────────────────────────────────────────────

it('redeem() throws ERR_QUOTE_001 for an expired quote', function (): void {
    $user = User::factory()->create();
    $id = (string) Str::uuid();

    PriceQuote::query()->create([
        'id'               => $id,
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => 'subscription_initial',
        'request_payload'  => [],
        'response_payload' => [],
        'entity_key'       => str_repeat('e', 64),
        'signature'        => str_repeat('f', 64),
        'terms_changed'    => false,
        'expires_at'       => now()->subMinute(),
    ]);

    /** @var QuoteService $service */
    $service = app(QuoteService::class);

    try {
        $service->redeem($id, $user, 'test');
        expect(true)->toBeFalse('Expected exception not thrown');
    } catch (QuoteRedemptionException $e) {
        expect($e->errorCode)->toBe('ERR_QUOTE_001');
    }
});

// ──────────────────────────────────────────────────────────────────────────────
// ERR_QUOTE_002 — payload mismatch (userOpHash)
// ──────────────────────────────────────────────────────────────────────────────

it('redeem() throws ERR_QUOTE_002 when userOpHash does not match', function (): void {
    $user = User::factory()->create();
    $storedHash = '0x' . str_repeat('aa', 32);
    $quote = makeLiveRedeemableQuote($user, 'send', $storedHash);

    /** @var QuoteService $service */
    $service = app(QuoteService::class);

    $wrongHash = '0x' . str_repeat('bb', 32);

    try {
        $service->redeem($quote->id, $user, 'wallet_send', $wrongHash);
        expect(true)->toBeFalse('Expected exception not thrown');
    } catch (QuoteRedemptionException $e) {
        expect($e->errorCode)->toBe('ERR_QUOTE_002');
    }
});

it('redeem() succeeds when userOpHash matches stored hash', function (): void {
    $user = User::factory()->create();
    $hash = '0x' . str_repeat('cc', 32);
    $quote = makeLiveRedeemableQuote($user, 'send', $hash);

    /** @var QuoteService $service */
    $service = app(QuoteService::class);
    $service->redeem($quote->id, $user, 'wallet_send', $hash);

    $quote->refresh();
    expect($quote->consumed_at)->not->toBeNull();
});
