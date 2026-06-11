<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\CardWaitlist;
use App\Domain\CardIssuance\Models\CardWaitlistDeposit;
use App\Domain\Pricing\Models\PriceQuote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Stripe\Service\Checkout\CheckoutServiceFactory;
use Stripe\Service\Checkout\SessionService;
use Stripe\Service\RefundService;
use Stripe\StripeClient;

// TestCase is bound folder-wide in tests/Pest.php — re-binding it here makes Pest refuse to load the file.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config(['cards.stripe_webhook_secret' => '']);
    config(['cards.allowed_return_urls' => [
        'zelta://cards/waitlist/deposit/success',
        'zelta://cards/waitlist/deposit/cancel',
    ]]);
});

/**
 * @return array{user: User, quote: PriceQuote, waitlist: CardWaitlist}
 */
function makeDepositFixtures(): array
{
    $user = User::factory()->create();

    $waitlist = CardWaitlist::query()->create([
        'id'        => (string) Str::uuid(),
        'user_id'   => $user->id,
        'position'  => 100,
        'joined_at' => now()->subDays(30),
    ]);

    $quote = PriceQuote::query()->create([
        'id'               => (string) Str::uuid(),
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => 'card_waitlist_deposit',
        'request_payload'  => ['kind' => 'card_waitlist_deposit'],
        'response_payload' => [
            'feeBreakdown' => [
                [
                    'label'  => 'deposit',
                    'amount' => ['amount' => '500', 'decimals' => 2, 'currency' => 'EUR'],
                ],
            ],
            'rates' => [],
        ],
        'entity_key'    => str_repeat('a', 64),
        'signature'     => str_repeat('b', 64),
        'terms_changed' => false,
        'expires_at'    => now()->addMinutes(5),
    ]);

    return ['user' => $user, 'quote' => $quote, 'waitlist' => $waitlist];
}

/**
 * Bind a stub StripeClient that returns a synthetic Checkout Session.
 *
 * @return SessionService&Mockery\MockInterface
 */
function bindStripeCheckoutCreate(string $sessionId, ?string $url = null, ?int $expiresAt = null): SessionService
{
    /** @var SessionService&Mockery\MockInterface $sessionService */
    $sessionService = Mockery::mock(SessionService::class);
    $sessionService->shouldReceive('create')
        ->andReturn(Stripe\Checkout\Session::constructFrom([
            'id'         => $sessionId,
            'url'        => $url ?? 'https://checkout.stripe.com/c/pay/' . $sessionId,
            'expires_at' => $expiresAt ?? now()->addHours(24)->getTimestamp(),
        ]));
    $sessionService->shouldReceive('expire')->byDefault()->andReturnUsing(static fn ($id) => Stripe\Checkout\Session::constructFrom(['id' => $id, 'status' => 'expired']));

    /** @var CheckoutServiceFactory&Mockery\MockInterface $checkout */
    $checkout = Mockery::mock(CheckoutServiceFactory::class);
    $checkout->sessions = $sessionService;

    /** @var RefundService&Mockery\MockInterface $refunds */
    $refunds = Mockery::mock(RefundService::class);
    $refunds->shouldReceive('create')->byDefault()
        ->andReturn(Stripe\Refund::constructFrom(['id' => 're_test_default']));

    /** @var StripeClient&Mockery\MockInterface $stripe */
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->checkout = $checkout;
    $stripe->refunds = $refunds;

    app()->instance(StripeClient::class, $stripe);

    return $sessionService;
}

// ─── POST /api/v1/cards/waitlist/deposit ───────────────────────────────────

it('starts a deposit, creates a Stripe session, persists a pending_payment row, redeems the quote', function () {
    ['user' => $user, 'quote' => $quote] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    bindStripeCheckoutCreate('cs_test_happy_001');

    $response = $this->postJson('/api/v1/cards/waitlist/deposit', [
        'quoteId' => $quote->id,
    ], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertOk();
    $response->assertJsonPath('checkoutUrl', 'https://checkout.stripe.com/c/pay/cs_test_happy_001');
    $response->assertJsonPath('sessionId', 'cs_test_happy_001');
    $response->assertJsonPath('depositAmount.amount', '500');
    $response->assertJsonPath('depositAmount.decimals', 2);
    $response->assertJsonPath('depositAmount.currency', 'EUR');

    $deposit = CardWaitlistDeposit::query()->where('user_id', $user->id)->first();
    expect($deposit)->not()->toBeNull();
    expect($deposit->status)->toBe('pending_payment');
    expect($deposit->stripe_checkout_session_id)->toBe('cs_test_happy_001');
    expect($deposit->deposit_amount_cents)->toBe(500);

    // Quote should be redeemed (consumed_at populated).
    $quote->refresh();
    expect($quote->consumed_at)->not()->toBeNull();
    expect($quote->consumed_by)->toBe('card_waitlist_deposit:pending');
});

it('rejects POST /deposit with missing Idempotency-Key — ERR_VALIDATION_001', function () {
    ['user' => $user, 'quote' => $quote] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/cards/waitlist/deposit', [
        'quoteId' => $quote->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_VALIDATION_001');
});

it('rejects POST /deposit when user has no card_waitlist row — ERR_CARDS_001', function () {
    $user = User::factory()->create();
    $quote = PriceQuote::query()->create([
        'id'               => (string) Str::uuid(),
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => 'card_waitlist_deposit',
        'request_payload'  => [],
        'response_payload' => [
            'feeBreakdown' => [[
                'label'  => 'deposit',
                'amount' => ['amount' => '500', 'decimals' => 2, 'currency' => 'EUR'],
            ]],
        ],
        'entity_key' => str_repeat('a', 64),
        'signature'  => str_repeat('b', 64),
        'expires_at' => now()->addMinutes(5),
    ]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    bindStripeCheckoutCreate('cs_test_unused');

    $response = $this->postJson('/api/v1/cards/waitlist/deposit', [
        'quoteId' => $quote->id,
    ], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(404);
    $response->assertJsonPath('error.code', 'ERR_CARDS_001');
});

it('rejects POST /deposit with wrong quote kind — ERR_CARDS_006', function () {
    ['user' => $user] = makeDepositFixtures();
    $sendQuote = PriceQuote::query()->create([
        'id'               => (string) Str::uuid(),
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => 'send',
        'request_payload'  => [],
        'response_payload' => ['feeBreakdown' => []],
        'entity_key'       => str_repeat('a', 64),
        'signature'        => str_repeat('b', 64),
        'expires_at'       => now()->addMinutes(5),
    ]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/cards/waitlist/deposit', [
        'quoteId' => $sendQuote->id,
    ], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error.code', 'ERR_CARDS_006');
});

it('rejects POST /deposit when user already has an active deposit — ERR_CARDS_002', function () {
    ['user' => $user, 'quote' => $quote] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    // Seed an active deposit.
    CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'pending_payment',
        'stripe_checkout_session_id' => 'cs_existing',
    ]);

    $response = $this->postJson('/api/v1/cards/waitlist/deposit', [
        'quoteId' => $quote->id,
    ], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error.code', 'ERR_CARDS_002');
});

it('rejects POST /deposit with an expired quote — ERR_QUOTE_001', function () {
    ['user' => $user] = makeDepositFixtures();
    $expired = PriceQuote::query()->create([
        'id'               => (string) Str::uuid(),
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => 'card_waitlist_deposit',
        'request_payload'  => [],
        'response_payload' => [
            'feeBreakdown' => [[
                'label'  => 'deposit',
                'amount' => ['amount' => '500', 'decimals' => 2, 'currency' => 'EUR'],
            ]],
        ],
        'entity_key' => str_repeat('a', 64),
        'signature'  => str_repeat('b', 64),
        'expires_at' => now()->subMinute(),
    ]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    bindStripeCheckoutCreate('cs_test_unused');

    $response = $this->postJson('/api/v1/cards/waitlist/deposit', [
        'quoteId' => $expired->id,
    ], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(410);
    $response->assertJsonPath('error.code', 'ERR_QUOTE_001');
});

it('rejects POST /deposit with an already-consumed quote — ERR_QUO_002', function () {
    ['user' => $user] = makeDepositFixtures();
    $consumed = PriceQuote::query()->create([
        'id'               => (string) Str::uuid(),
        'user_id'          => $user->id,
        'user_tier'        => 'free',
        'kind'             => 'card_waitlist_deposit',
        'request_payload'  => [],
        'response_payload' => [
            'feeBreakdown' => [[
                'label'  => 'deposit',
                'amount' => ['amount' => '500', 'decimals' => 2, 'currency' => 'EUR'],
            ]],
        ],
        'entity_key'  => str_repeat('a', 64),
        'signature'   => str_repeat('b', 64),
        'expires_at'  => now()->addMinutes(5),
        'consumed_at' => now()->subMinute(),
        'consumed_by' => 'card_waitlist_deposit:done',
    ]);
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    bindStripeCheckoutCreate('cs_test_unused');

    $response = $this->postJson('/api/v1/cards/waitlist/deposit', [
        'quoteId' => $consumed->id,
    ], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(409);
    $response->assertJsonPath('error.code', 'ERR_QUO_002');
});

it('rejects POST /deposit with successUrl not in allow-list — ERR_CARDS_005', function () {
    ['user' => $user, 'quote' => $quote] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/cards/waitlist/deposit', [
        'quoteId'    => $quote->id,
        'successUrl' => 'https://evil.example/steal',
    ], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('error.code', 'ERR_CARDS_005');
});

// ─── POST /api/v1/cards/waitlist/deposit/cancel ─────────────────────────────

it('cancels a pending_payment deposit and expires the Stripe session', function () {
    ['user' => $user] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $deposit = CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'pending_payment',
        'stripe_checkout_session_id' => 'cs_pending_001',
    ]);

    /** @var SessionService&Mockery\MockInterface $sessions */
    $sessions = Mockery::mock(SessionService::class);
    $sessions->shouldReceive('expire')->once()->with('cs_pending_001')
        ->andReturn(Stripe\Checkout\Session::constructFrom(['id' => 'cs_pending_001', 'status' => 'expired']));

    /** @var CheckoutServiceFactory&Mockery\MockInterface $checkout */
    $checkout = Mockery::mock(CheckoutServiceFactory::class);
    $checkout->sessions = $sessions;

    /** @var RefundService&Mockery\MockInterface $refunds */
    $refunds = Mockery::mock(RefundService::class);

    /** @var StripeClient&Mockery\MockInterface $stripe */
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->checkout = $checkout;
    $stripe->refunds = $refunds;
    app()->instance(StripeClient::class, $stripe);

    $response = $this->postJson('/api/v1/cards/waitlist/deposit/cancel', [], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'refunded');
    $response->assertJsonPath('estimatedSettlementDays', 0);

    $deposit->refresh();
    expect($deposit->status)->toBe('refunded');
});

it('cancels a paid deposit and calls Stripe refunds.create', function () {
    ['user' => $user] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $deposit = CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'paid',
        'stripe_checkout_session_id' => 'cs_paid_001',
        'stripe_payment_intent_id'   => 'pi_paid_001',
        'paid_at'                    => now()->subHour(),
        'refund_eligible_after'      => now()->addMonths(18),
    ]);

    /** @var RefundService&Mockery\MockInterface $refunds */
    $refunds = Mockery::mock(RefundService::class);
    $refunds->shouldReceive('create')->once()
        ->andReturn(Stripe\Refund::constructFrom(['id' => 're_test_001']));

    /** @var SessionService&Mockery\MockInterface $sessions */
    $sessions = Mockery::mock(SessionService::class);
    /** @var CheckoutServiceFactory&Mockery\MockInterface $checkout */
    $checkout = Mockery::mock(CheckoutServiceFactory::class);
    $checkout->sessions = $sessions;

    /** @var StripeClient&Mockery\MockInterface $stripe */
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->checkout = $checkout;
    $stripe->refunds = $refunds;
    app()->instance(StripeClient::class, $stripe);

    $response = $this->postJson('/api/v1/cards/waitlist/deposit/cancel', [], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'cancellation_requested');
    $response->assertJsonPath('estimatedSettlementDays', 10);

    $deposit->refresh();
    expect($deposit->status)->toBe('cancellation_requested');
    expect($deposit->stripe_refund_id)->toBe('re_test_001');
});

it('cancel returns ERR_CARDS_003 when no active deposit exists', function () {
    ['user' => $user] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/v1/cards/waitlist/deposit/cancel', [], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertStatus(404);
    $response->assertJsonPath('error.code', 'ERR_CARDS_003');
});

it('cancel lands in refund_pending_manual on Stripe API failure (Q9.4 — never block user)', function () {
    ['user' => $user] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $deposit = CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'paid',
        'stripe_checkout_session_id' => 'cs_paid_002',
        'stripe_payment_intent_id'   => 'pi_paid_002',
        'paid_at'                    => now()->subHour(),
        'refund_eligible_after'      => now()->addMonths(18),
    ]);

    /** @var RefundService&Mockery\MockInterface $refunds */
    $refunds = Mockery::mock(RefundService::class);
    $refunds->shouldReceive('create')->once()
        ->andThrow(new RuntimeException('Stripe 503'));

    /** @var SessionService&Mockery\MockInterface $sessions */
    $sessions = Mockery::mock(SessionService::class);
    /** @var CheckoutServiceFactory&Mockery\MockInterface $checkout */
    $checkout = Mockery::mock(CheckoutServiceFactory::class);
    $checkout->sessions = $sessions;

    /** @var StripeClient&Mockery\MockInterface $stripe */
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->checkout = $checkout;
    $stripe->refunds = $refunds;
    app()->instance(StripeClient::class, $stripe);

    $response = $this->postJson('/api/v1/cards/waitlist/deposit/cancel', [], [
        'Idempotency-Key' => Str::random(24),
    ]);

    $response->assertOk();
    $response->assertJsonPath('status', 'refund_pending_manual');

    $deposit->refresh();
    expect($deposit->status)->toBe('refund_pending_manual');
});

// ─── GET /api/v1/cards/waitlist/entry ───────────────────────────────────────

it('GET /entry returns tier=none when user is not on the waitlist', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->getJson('/api/v1/cards/waitlist/entry');

    $response->assertOk();
    $response->assertJsonPath('tier', 'none');
    $response->assertJsonPath('depositStatus', null);
    $response->assertJsonPath('queuePosition', null);
});

it('GET /entry returns tier=free + queuePosition for a waitlist member with no deposit', function () {
    ['user' => $user] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->getJson('/api/v1/cards/waitlist/entry');

    $response->assertOk();
    $response->assertJsonPath('tier', 'free');
    $response->assertJsonPath('depositStatus', 'none');
    expect($response->json('queuePosition'))->toBeInt();
});

it('GET /entry returns tier=deposit for pending_payment + frozen refundEligibleAfter for paid', function () {
    ['user' => $user, 'quote' => $quote] = makeDepositFixtures();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $paidAt = now()->subDay();
    $eligibleAfter = $paidAt->copy()->addMonths(18);

    CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => $quote->id,
        'status'                     => 'paid',
        'stripe_checkout_session_id' => 'cs_entry_paid',
        'stripe_payment_intent_id'   => 'pi_entry_paid',
        'paid_at'                    => $paidAt,
        'refund_eligible_after'      => $eligibleAfter,
    ]);

    $response = $this->getJson('/api/v1/cards/waitlist/entry');

    $response->assertOk();
    $response->assertJsonPath('tier', 'deposit');
    $response->assertJsonPath('depositStatus', 'paid');
    $response->assertJsonPath('depositAmount.amount', '500');
    expect($response->json('refundEligibleAfter'))->not()->toBeNull();
});

it('GET /entry queue position ranks paid-deposit users ahead of free-tier members', function () {
    // user A: paid; joined first
    $userA = User::factory()->create();
    CardWaitlist::query()->create([
        'id'        => (string) Str::uuid(),
        'user_id'   => $userA->id,
        'position'  => 1,
        'joined_at' => now()->subDays(10),
    ]);
    CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $userA->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'paid',
        'stripe_checkout_session_id' => 'cs_A',
        'stripe_payment_intent_id'   => 'pi_A',
        'paid_at'                    => now()->subDay(),
        'refund_eligible_after'      => now()->addMonths(18),
    ]);

    // user B: paid; joined later than A
    $userB = User::factory()->create();
    CardWaitlist::query()->create([
        'id'        => (string) Str::uuid(),
        'user_id'   => $userB->id,
        'position'  => 2,
        'joined_at' => now()->subDays(5),
    ]);
    CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $userB->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'paid',
        'stripe_checkout_session_id' => 'cs_B',
        'stripe_payment_intent_id'   => 'pi_B',
        'paid_at'                    => now()->subDay(),
        'refund_eligible_after'      => now()->addMonths(18),
    ]);

    // user C: free, joined before B but after A
    $userC = User::factory()->create();
    CardWaitlist::query()->create([
        'id'        => (string) Str::uuid(),
        'user_id'   => $userC->id,
        'position'  => 3,
        'joined_at' => now()->subDays(7),
    ]);

    Sanctum::actingAs($userC, ['read', 'write', 'delete']);
    $response = $this->getJson('/api/v1/cards/waitlist/entry');
    $response->assertOk();
    // C should be position 3: A(1), B(2), then C(3).
    expect($response->json('queuePosition'))->toBe(3);

    Sanctum::actingAs($userA, ['read', 'write', 'delete']);
    $responseA = $this->getJson('/api/v1/cards/waitlist/entry');
    expect($responseA->json('queuePosition'))->toBe(1);

    Sanctum::actingAs($userB, ['read', 'write', 'delete']);
    $responseB = $this->getJson('/api/v1/cards/waitlist/entry');
    expect($responseB->json('queuePosition'))->toBe(2);
});
