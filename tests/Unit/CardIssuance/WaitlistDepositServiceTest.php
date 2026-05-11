<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\CardWaitlist;
use App\Domain\CardIssuance\Models\CardWaitlistDeposit;
use App\Domain\CardIssuance\Services\WaitlistDepositService;
use App\Domain\Pricing\Services\PriceQuoteIssuer;
use App\Domain\Pricing\Services\QuoteService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Stripe\Service\Checkout\CheckoutServiceFactory;
use Stripe\Service\RefundService;
use Stripe\StripeClient;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    config(['cards.allowed_return_urls' => ['zelta://cards/waitlist/deposit/success', 'zelta://cards/waitlist/deposit/cancel']]);
});

/**
 * Create a service with a fake Stripe client bound in the container.
 * The service now resolves StripeClient lazily via `app()`.
 */
function makeServiceWithStripe(StripeClient $stripe): WaitlistDepositService
{
    app()->instance(StripeClient::class, $stripe);

    $quoteService = app(QuoteService::class);
    if (! $quoteService instanceof QuoteService) {
        $quoteService = new QuoteService(app(PriceQuoteIssuer::class));
    }

    return new WaitlistDepositService($quoteService);
}

/**
 * Build a minimal Mockery StripeClient stub. `$stripe->checkout` and
 * `$stripe->refunds` are mocked as their factory / service classes (which
 * are the types StripeClient's @property docblock declares). Property
 * assignment is OK because Mockery's mock IS an instance of the mocked
 * class — PHPStan sees the static type via the @var annotations.
 */
function makeStubStripe(): StripeClient
{
    /** @var CheckoutServiceFactory&Mockery\MockInterface $checkout */
    $checkout = Mockery::mock(CheckoutServiceFactory::class);
    /** @var RefundService&Mockery\MockInterface $refunds */
    $refunds = Mockery::mock(RefundService::class);

    /** @var StripeClient&Mockery\MockInterface $stripe */
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->checkout = $checkout;
    $stripe->refunds = $refunds;

    return $stripe;
}

it('entryFor returns tier=none when user has no card_waitlist row', function () {
    $user = User::factory()->create();

    $service = makeServiceWithStripe(makeStubStripe());
    $entry = $service->entryFor($user);

    expect($entry['tier'])->toBe('none');
    expect($entry['depositStatus'])->toBeNull();
    expect($entry['queuePosition'])->toBeNull();
});

it('entryFor freezes refundEligibleAfter at the value stored on the deposit (Q9.2)', function () {
    $user = User::factory()->create();
    CardWaitlist::query()->create([
        'id'        => (string) Str::uuid(),
        'user_id'   => $user->id,
        'position'  => 1,
        'joined_at' => now()->subDays(30),
    ]);

    $paidAt = Carbon::parse('2026-01-01T00:00:00Z');
    $expectedFrozen = Carbon::parse('2027-07-01T00:00:00Z'); // paid_at + 18 months

    CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'paid',
        'stripe_checkout_session_id' => 'cs_frozen_001',
        'stripe_payment_intent_id'   => 'pi_frozen_001',
        'paid_at'                    => $paidAt,
        'refund_eligible_after'      => $expectedFrozen,
    ]);

    $stripe = makeStubStripe();

    $service = makeServiceWithStripe($stripe);
    $entry = $service->entryFor($user);

    expect($entry['tier'])->toBe('deposit');
    expect($entry['depositStatus'])->toBe('paid');
    expect($entry['refundEligibleAfter'])->not()->toBeNull();

    // Frozen — even if policy were to change, the stored value is what we return.
    $stored = Carbon::parse((string) $entry['refundEligibleAfter']);
    expect($stored->equalTo($expectedFrozen))->toBeTrue();
});

it('all deposit amounts in the response shape are integer-string smallest-unit (no float)', function () {
    $user = User::factory()->create();
    CardWaitlist::query()->create([
        'id'        => (string) Str::uuid(),
        'user_id'   => $user->id,
        'position'  => 1,
        'joined_at' => now()->subDays(2),
    ]);
    CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'paid',
        'stripe_checkout_session_id' => 'cs_money_check',
        'stripe_payment_intent_id'   => 'pi_money_check',
        'paid_at'                    => now()->subHour(),
        'refund_eligible_after'      => now()->addMonths(18),
        'deposit_amount_cents'       => 500,
        'deposit_decimals'           => 2,
        'deposit_currency'           => 'EUR',
    ]);

    $stripe = makeStubStripe();

    $service = makeServiceWithStripe($stripe);
    $entry = $service->entryFor($user);

    /** @var array<string, mixed> $amount */
    $amount = $entry['depositAmount'];
    expect($amount['amount'])->toBe('500');
    expect($amount['amount'])->toBeString();
    expect($amount['decimals'])->toBe(2);
    expect($amount['currency'])->toBe('EUR');
});

it('applyChargeRefunded writes a negative-amount outbox row per ADR-0004 sign-prefix', function () {
    $user = User::factory()->create();
    CardWaitlistDeposit::query()->create([
        'id'                         => (string) Str::uuid(),
        'user_id'                    => $user->id,
        'quote_id'                   => (string) Str::uuid(),
        'status'                     => 'paid',
        'stripe_checkout_session_id' => 'cs_neg_outbox',
        'stripe_payment_intent_id'   => 'pi_neg_outbox',
        'paid_at'                    => now()->subHour(),
        'refund_eligible_after'      => now()->addMonths(18),
    ]);

    $stripe = makeStubStripe();

    $service = makeServiceWithStripe($stripe);
    $service->applyChargeRefunded([
        'id'              => 'ch_neg_outbox',
        'payment_intent'  => 'pi_neg_outbox',
        'amount_refunded' => 500,
        'currency'        => 'eur',
    ], 'evt_neg_outbox');

    $outbox = App\Domain\Subscription\Models\RevenueOutboxEvent::query()->first();
    expect($outbox)->not()->toBeNull();
    expect($outbox->payload['amount'])->toBe(-500);
});
