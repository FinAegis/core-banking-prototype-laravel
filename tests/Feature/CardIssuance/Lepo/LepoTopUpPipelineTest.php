<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Events\Broadcast\CardTopUpUpdated;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardLifecycle;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUpPipeline;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url'             => 'https://lepo.test/api/v2', 'cardissuance.issuers.lepo.api_key' => 'k',
        'cardissuance.issuers.lepo.card_type_id_virtual' => '145', 'cardissuance.providers.lepo_topups_enabled' => true,
    ]);
    Event::fake([CardTopUpUpdated::class]);
});

function plTopUp(string $cardStatus = 'pending', ?string $token = null, string $status = 'deposit_received'): CardTopUp
{
    $user = User::factory()->create();
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+37060000000', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345']);
    $holder->save();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'provider' => 'lepo', 'status' => 'active']);
    $card = Card::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'funding_account_id' => $account->id, 'issuer' => 'lepo', 'status' => $cardStatus,
        'issuer_card_token'         => $token, 'last4' => $token === null ? null : '1234', 'network' => $token === null ? null : 'visa']);
    $topup = new CardTopUp();
    $topup->forceFill([
        'user_id'             => $user->id, 'card_id' => $card->id, 'funding_account_id' => $account->id, 'provider' => 'lepo', 'source' => 'app', 'asset' => 'USDC',
        'network'             => 'arbitrum', 'amount' => '25.000000', 'received_amount' => '25.000000', 'deposit_address' => '0xarb', 'status' => $status,
        'provider_deposit_id' => 'dep-1', 'expires_at' => now()->addHour(),
    ])->save();

    return $topup;
}

/**
 * @param  array<string, mixed>  $over  responses by step: convert, usd, usd_after_open, create_card, load
 */
function plLepo(array $over = []): void
{
    $opened = false;
    // A list of responses is answered in order, the last one repeating.
    $next = function (string $key) use (&$over) {
        if (! isset($over[$key]) || ! is_array($over[$key])) {
            return $over[$key] ?? null;
        }

        return count($over[$key]) > 1 ? array_shift($over[$key]) : $over[$key][0];
    };
    Http::fake(function (Request $r) use ($over, &$opened, $next) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        if (str_ends_with($path, '/transfers/crypto-to-quantum-transfer')) {
            return $over['convert'] ?? Http::response(['success' => true, 'transferId' => 'tr-1', 'details' => ['status' => 'COMPLETED']]);
        }
        if (str_ends_with($path, '/transfers/tr-1')) {
            return $next('transfer') ?? Http::response(['id' => 'tr-1', 'status' => 'COMPLETED']);
        }
        if (str_ends_with($path, '/users/12345/assets')) {
            // Opening the card takes the 1 USD issue fee from the balance.
            $usd = $opened ? ($over['usd_after_open'] ?? '23.90') : ($over['usd'] ?? '24.90');

            return $next('assets') ?? Http::response(['userId' => 12345, 'assets' => [['currency' => 'USD', 'balance' => $usd, 'availableBalance' => $usd], ['currency' => 'USDC', 'balance' => '0', 'availableBalance' => '0']]]);
        }
        if ($r->method() === 'POST' && str_ends_with($path, '/cards/topup')) {
            return $over['load'] ?? Http::response(['success' => true, 'orderId' => 'ord-1', 'value' => (float) $r['value'], 'fee' => 0.24, 'transakUrl' => null, 'isPartial' => false]);
        }
        if ($r->method() === 'POST' && str_ends_with($path, '/cards')) {
            $opened = true;

            return $over['create_card'] ?? Http::response(['id' => 777, 'status' => 'ACTIVE', 'cardNumber' => '****1234', 'cardNetwork' => 'Visa', 'balance' => ['available' => '0', 'pending' => '0', 'frozen' => '0']], 201);
        }
        if ($r->method() === 'GET' && str_ends_with($path, '/cards')) {
            return Http::response(['cards' => []]);
        }

        return Http::response(['id' => 777, 'status' => 'ACTIVE', 'cardNumber' => '****1234', 'balance' => ['available' => '23.90', 'pending' => '0', 'frozen' => '0']]);
    });
}

function plCalls(string $suffix, string $method = 'POST'): int
{
    return collect(Http::recorded())->filter(fn (array $pair): bool => $pair[0]->method() === $method && str_ends_with((string) parse_url($pair[0]->url(), PHP_URL_PATH), $suffix))->count();
}

it('converts, opens the card and loads it on the first top-up', function (): void {
    $topup = plTopUp();
    plLepo();

    $done = app(LepoTopUpPipeline::class)->advance($topup);

    expect($done->status)->toBe('completed')
        ->and($done->provider_conversion_id)->toBe('tr-1')
        ->and($done->provider_load_id)->toBe('ord-1')
        ->and($done->credited_cents)->toBe(2390)
        ->and($done->issuance_fee_cents)->toBe(100)
        ->and($done->completed_at)->not->toBeNull()
        ->and($done->card?->fresh()?->issuer_card_token)->toBe('777');
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/cards/topup') && $r['cardId'] === '777' && $r['value'] === '23.90');
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'crypto-to-quantum-transfer') && $r['sourceCurrency'] === 'USDC' && $r['amount'] === '25.000000');
    Event::assertDispatched(CardTopUpUpdated::class, fn (CardTopUpUpdated $e): bool => $e->status === 'completed' && $e->creditedCents === 2390);
});

it('loads an already open card without opening it', function (): void {
    $topup = plTopUp('active', '777');
    plLepo();

    expect(app(LepoTopUpPipeline::class)->advance($topup)->status)->toBe('completed');
    expect(plCalls('/cards'))->toBe(0)->and(plCalls('/cards/topup'))->toBe(1);
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/cards/topup') && $r['value'] === '24.90');
});

it('stops in needs_attention when the conversion fails or times out', function (): void {
    $topup = plTopUp();
    plLepo(['convert' => Http::response(['message' => 'gateway timeout'], 504)]);

    $stopped = app(LepoTopUpPipeline::class)->advance($topup);
    app(LepoTopUpPipeline::class)->advance($stopped);

    expect($stopped->status)->toBe('needs_attention')->and($stopped->last_error)->toBe('CONVERSION_UNCLEAR');
    expect(plCalls('/transfers/crypto-to-quantum-transfer'))->toBe(1)->and(plCalls('/cards/topup'))->toBe(0);
});

it('stops in needs_attention when the load answers with a checkout link', function (): void {
    $topup = plTopUp('active', '777');
    plLepo(['load' => Http::response(['success' => true, 'transakUrl' => 'https://global.transak.com/x', 'orderId' => 'o2'])]);

    $stopped = app(LepoTopUpPipeline::class)->advance($topup);

    expect($stopped->status)->toBe('needs_attention')->and($stopped->last_error)->toBe('LOAD_CHECKOUT_LINK');
});

it('stops in needs_attention when the load fails', function (): void {
    $topup = plTopUp('active', '777');
    plLepo(['load' => Http::response(['message' => 'down'], 500)]);

    expect(app(LepoTopUpPipeline::class)->advance($topup)->last_error)->toBe('LOAD_UNCLEAR');
    expect(plCalls('/cards/topup'))->toBe(1);
});

it('stops in needs_attention when the card cannot be paid for', function (): void {
    $topup = plTopUp();
    plLepo(['create_card' => Http::response(['id' => 777, 'status' => 'PAYMENT_REQUIRED', 'cardNumber' => ''], 201)]);

    $stopped = app(LepoTopUpPipeline::class)->advance($topup);

    expect($stopped->status)->toBe('needs_attention')->and($stopped->last_error)->toBe('CARD_PAYMENT_REQUIRED');
    expect(plCalls('/cards/topup'))->toBe(0);
});

it('stops a deposit too small to load before converting it', function (): void {
    $topup = plTopUp('active', '777');
    $topup->forceFill(['received_amount' => '4.990000'])->save();
    plLepo();

    $stopped = app(LepoTopUpPipeline::class)->advance($topup);

    expect($stopped->status)->toBe('needs_attention')->and($stopped->last_error)->toBe('BELOW_MINIMUM');
    Http::assertNothingSent();
});

it('stops before converting when no card can take the money', function (string $cardStatus): void {
    $topup = plTopUp($cardStatus, '777');
    plLepo();

    $stopped = app(LepoTopUpPipeline::class)->advance($topup);

    expect($stopped->status)->toBe('needs_attention')->and($stopped->last_error)->toBe('NO_LOADABLE_CARD');
    Http::assertNothingSent();
})->with(['cancelled', 'blocked', 'expired']);

it('never converts for a card that waits for payment', function (): void {
    $topup = plTopUp('payment_required', '777');
    plLepo();

    expect(app(LepoTopUpPipeline::class)->advance($topup)->last_error)->toBe('CARD_PAYMENT_REQUIRED');
    Http::assertNothingSent();
});

it('loads the user\'s current card when the top-up\'s card was cancelled', function (): void {
    $topup = plTopUp('cancelled', '777');
    $old = $topup->card()->firstOrFail();
    $current = Card::create(['user_id' => $old->user_id, 'cardholder_id' => $old->cardholder_id, 'funding_account_id' => $old->funding_account_id, 'issuer' => 'lepo',
        'status'                       => 'active', 'issuer_card_token' => '999', 'last4' => '9999', 'network' => 'visa']);
    plLepo();

    $done = app(LepoTopUpPipeline::class)->advance($topup);

    expect($done->status)->toBe('completed')->and($done->card_id)->toBe($current->id);
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/cards/topup') && $r['cardId'] === '999');
});

it('loads no more than this deposit brought', function (): void {
    $topup = plTopUp('active', '777');
    plLepo(['usd' => '124.90']);

    expect(app(LepoTopUpPipeline::class)->advance($topup)->credited_cents)->toBe(2500);
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/cards/topup') && $r['value'] === '25.00');
});

it('waits while an earlier top-up of the user has an unclear outcome', function (): void {
    $topup = plTopUp('active', '777');
    $earlier = new CardTopUp();
    $earlier->forceFill(array_merge($topup->only(['user_id', 'card_id', 'funding_account_id', 'provider', 'asset', 'network', 'deposit_address']), [
        'source' => 'app', 'amount' => '10.000000', 'status' => 'needs_attention', 'last_error' => 'LOAD_UNCLEAR', 'provider_deposit_id' => 'dep-0', 'expires_at' => now(),
    ]))->save();
    plLepo();

    expect(app(LepoTopUpPipeline::class)->advance($topup)->status)->toBe('deposit_received');
    Http::assertNothingSent();
});

it('credits what Lepo says it loaded', function (): void {
    $topup = plTopUp('active', '777');
    plLepo(['load' => Http::response(['success' => true, 'orderId' => 'ord-2', 'value' => '24.90', 'fee' => '0.30', 'isPartial' => false])]);

    $done = app(LepoTopUpPipeline::class)->advance($topup);

    expect($done->credited_cents)->toBe(2490)->and($done->fee_cents)->toBe(30);
});

it('stops when Lepo loaded only part or a different amount', function (array $answer, string $reason): void {
    $topup = plTopUp('active', '777');
    plLepo(['load' => Http::response(['success' => true, 'orderId' => 'ord-3'] + $answer)]);

    $stopped = app(LepoTopUpPipeline::class)->advance($topup);

    expect($stopped->status)->toBe('needs_attention')->and($stopped->last_error)->toBe($reason);
})->with([
    'partial'   => [['value' => 10.0, 'isPartial' => true], 'LOAD_PARTIAL'],
    'different' => [['value' => 10.0, 'isPartial' => false], 'LOAD_AMOUNT_MISMATCH'],
    'no value'  => [['isPartial' => false], 'LOAD_AMOUNT_MISMATCH'],
]);

it('waits for a pending conversion before loading', function (): void {
    $topup = plTopUp('active', '777');
    plLepo([
        'convert'  => Http::response(['success' => true, 'transferId' => 'tr-1', 'details' => ['status' => 'PENDING']]),
        'transfer' => [Http::response(['id' => 'tr-1', 'status' => 'PROCESSING']), Http::response(['id' => 'tr-1', 'status' => 'PROCESSING']), Http::response(['id' => 'tr-1', 'status' => 'COMPLETED'])],
    ]);

    $waiting = app(LepoTopUpPipeline::class)->advance($topup);
    expect($waiting->status)->toBe('converting')->and($waiting->step_started_at)->toBeNull()->and(plCalls('/cards/topup'))->toBe(0);

    app(LepoTopUpPipeline::class)->advance($waiting);
    expect($waiting->fresh()?->status)->toBe('converting')->and(plCalls('/cards/topup'))->toBe(0);

    expect(app(LepoTopUpPipeline::class)->advance($waiting)->status)->toBe('completed');
    expect(plCalls('/transfers/crypto-to-quantum-transfer'))->toBe(1);
});

it('retries the load preparation instead of stopping', function (): void {
    $topup = plTopUp('active', '777');
    // The client retries a read twice, so the first run sees three failures.
    $busy = Http::response(['message' => 'busy'], 503);
    plLepo(['assets' => [$busy, $busy, $busy, Http::response(['assets' => [['currency' => 'USD', 'availableBalance' => '24.90']]])]]);

    $waiting = app(LepoTopUpPipeline::class)->advance($topup);
    expect($waiting->status)->toBe('converting')->and($waiting->last_error)->toBe('BALANCE_UNREADABLE');

    expect(app(LepoTopUpPipeline::class)->advance($waiting)->status)->toBe('completed');
    expect(plCalls('/transfers/crypto-to-quantum-transfer'))->toBe(1);
});

it('stops a load preparation that keeps failing for an hour', function (): void {
    $topup = plTopUp('active', '777');
    plLepo(['assets' => Http::response(['message' => 'busy'], 503)]);
    $waiting = app(LepoTopUpPipeline::class)->advance($topup);

    $this->travel(61)->minutes();

    expect(app(LepoTopUpPipeline::class)->advance($waiting)->status)->toBe('needs_attention')
        ->and($waiting->last_error)->toBe('BALANCE_UNREADABLE');
});

it('reads only the available US-dollar balance', function (): void {
    $topup = plTopUp('active', '777');
    plLepo(['assets' => Http::response(['assets' => [['currency' => 'USD', 'balance' => '30.00', 'availableBalance' => null]]])]);

    expect(app(LepoTopUpPipeline::class)->advance($topup)->last_error)->toBe('BALANCE_UNREADABLE');
    expect(plCalls('/cards/topup'))->toBe(0);
});

it('never calls Lepo again for a step already in flight', function (string $status): void {
    $topup = plTopUp('active', '777', $status);
    $topup->forceFill(['step_started_at' => now()->subMinutes(2)])->save();
    plLepo();

    expect(app(LepoTopUpPipeline::class)->advance($topup)->status)->toBe($status);
    Http::assertNothingSent();

    $this->travel(20)->minutes();
    expect(app(LepoTopUpPipeline::class)->advance($topup)->status)->toBe('needs_attention')
        ->and($topup->last_error)->toBe('STEP_INTERRUPTED');
    Http::assertNothingSent();
})->with(['converting', 'loading']);

it('does nothing while the switch is off', function (): void {
    config(['cardissuance.providers.lepo_topups_enabled' => false]);
    $topup = plTopUp();
    plLepo();

    expect(app(LepoTopUpPipeline::class)->advance($topup)->status)->toBe('deposit_received');
    Http::assertNothingSent();
});

it('processes one top-up per user at a time', function (): void {
    $topup = plTopUp();
    plLepo();
    $lock = Cache::lock("cards:lepo:money:{$topup->user_id}", 60);
    $lock->get();

    expect(app(LepoTopUpPipeline::class)->advance($topup)->status)->toBe('deposit_received');
    Http::assertNothingSent();
    $lock->release();
});

it('makes a card cancel wait for the money lock', function (): void {
    $topup = plTopUp('active', '777');
    plLepo();
    $lock = Cache::lock("cards:lepo:money:{$topup->user_id}", 60);
    $lock->get();

    $card = $topup->card()->firstOrFail();
    expect(fn () => app(LepoCardLifecycle::class)->cancel($card))->toThrow(CardProviderException::class);
    Http::assertNothingSent();
    $lock->release();
});
