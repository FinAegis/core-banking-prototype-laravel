<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\CardIssuance\Events\Broadcast\CardWithdrawalUpdated;
use App\Domain\CardIssuance\Jobs\AdvanceLepoWithdrawals;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Models\CardWithdrawalEvent;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

const WD_ADDRESS = '0x1111111111111111111111111111111111111111';

beforeEach(function (): void {
    config([
        'cache.default'                                        => 'array',
        'cardissuance.issuers.lepo.base_url'                   => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'                    => 'test-key',
        'cardissuance.providers.enabled'                       => 'lepo',
        'cardissuance.providers.default'                       => 'lepo',
        'cardissuance.providers.lepo_pilot_only'               => false,
        'cardissuance.providers.lepo_deposit_networks'         => 'arbitrum,polygon',
        'cardissuance.providers.lepo_topups_enabled'           => true,
        'cardissuance.providers.lepo_withdrawals_enabled'      => true,
        'cardissuance.providers.lepo_withdrawal_networks'      => 'arbitrum,polygon',
        'cardissuance.providers.lepo_withdrawal_chain_codes'   => 'arbitrum:ARB,polygon:MATIC',
        'cardissuance.providers.lepo_withdrawal_min'           => '5.00',
        'cardissuance.providers.lepo_withdrawal_max'           => '1000.00',
        'cardissuance.providers.lepo_withdrawal_daily_max'     => '2000.00',
        'cardissuance.providers.lepo_withdrawal_daily_count'   => 3,
        'cardissuance.providers.lepo_withdrawal_address_hours' => 24,
    ]);
    Http::preventStrayRequests();
    Bus::fake([AdvanceLepoWithdrawals::class]);
    Event::fake([CardWithdrawalUpdated::class]);
});

/**
 * A user with an open Lepo card (Lepo card 777, Lepo user 12345) and a Privy
 * smart-account address on Arbitrum registered two days ago.
 */
function wdCard(string $status = 'active', ?string $token = '777', ?User $user = null, bool $withAddress = true): Card
{
    $user ??= User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified', 'verified_at' => now(), 'issuer_cardholder_id' => '12345']);
    $holder->save();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'provider' => 'lepo', 'status' => 'active']);
    if ($withAddress) {
        wdAddress($user, 'arbitrum', WD_ADDRESS, now()->subDays(2));
    }

    return Card::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'funding_account_id' => $account->id, 'issuer' => 'lepo', 'status' => $status,
        'issuer_card_token'        => $token, 'last4' => $token === null ? null : '1234', 'network' => $token === null ? null : 'visa', 'balance_cents' => 6210]);
}

/**
 * @param  array<string, mixed>|null  $metadata
 */
function wdAddress(User $user, string $chain, string $address, Carbon\CarbonInterface $createdAt, ?array $metadata = null): BlockchainAddress
{
    $row = BlockchainAddress::create([
        'user_uuid' => $user->uuid, 'chain' => $chain, 'address' => $address, 'public_key' => $address, 'is_active' => true,
        'metadata'  => $metadata ?? ['provider' => 'privy', 'wallet_kind' => 'privy_smart_account'],
    ]);
    $row->forceFill(['created_at' => $createdAt])->save();

    return $row;
}

/**
 * @param  array<string, mixed>  $over  available, rate, fee, fee_currency, withdrawal_fee, confirm, resend
 */
function wdLepo(array $over = []): void
{
    Http::fake(function (Request $r) use ($over) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        if ($r->method() === 'GET' && str_ends_with($path, '/cards/quantum-topup/estimate')) {
            return Http::response(['success' => true, 'usdAmount' => 50,
                'usdToUsdc'                  => ['baseCurrency' => 'USD', 'quoteCurrency' => 'USDC', 'baseAmount' => '50', 'rfqAmount' => '49.65', 'fee' => $over['fee'] ?? '0.25',
                    'feeCurrency'                               => $over['fee_currency'] ?? 'USD', 'rate' => $over['rate'] ?? '0.998', 'quoteId' => 'q1', 'ttlMs' => '60000']]);
        }
        if ($r->method() === 'GET' && str_ends_with($path, '/transfers/withdrawals/fee-and-quota')) {
            return Http::response(['code' => '0', 'message' => 'ok', 'data' => ['fees' => [['amount' => $over['withdrawal_fee'] ?? '1.000000', 'currency' => 'USDC', 'type' => 'network']]]]);
        }
        if ($r->method() === 'POST' && str_ends_with($path, '/transfers/withdrawals/crypto/confirm')) {
            $confirm = $over['confirm'] ?? null;
            if (is_callable($confirm)) {
                return $confirm($r);
            }

            return $confirm ?? Http::response(['success' => true, 'transactionId' => 'wd-tx-1', 'interlaceTransferId' => 'il-1', 'status' => 'PROCESSING', 'actualAmount' => 48.65, 'currency' => 'USDC',
                'fees'                                   => [['amount' => '1.00', 'currency' => 'USDC', 'type' => 'network']], 'otpRequired' => true]);
        }
        if ($r->method() === 'POST' && str_ends_with($path, '/transfers/withdrawals/crypto/resend')) {
            return $over['resend'] ?? Http::response(['success' => true, 'message' => 'sent', 'expiresAt' => '2030-01-01T10:20:00Z']);
        }
        if ($r->method() === 'GET' && str_ends_with($path, '/cards/777')) {
            $available = $over['available'] ?? '62.10';

            return Http::response(['id' => 777, 'status' => 'ACTIVE', 'cardNumber' => '****1234', 'balance' => ['available' => $available, 'pending' => '0', 'frozen' => '0']]);
        }

        return Http::response(['message' => 'unexpected'], 500);
    });
}

/**
 * @param  array<string, mixed>  $body
 * @return Illuminate\Testing\TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function wdStart(PHPUnit\Framework\TestCase $test, Card $card, array $body = ['amount' => '50.00', 'network' => 'arbitrum'], ?string $key = null): Illuminate\Testing\TestResponse
{
    assert($test instanceof Tests\TestCase);

    return $test->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson("/api/v1/cards/issued/{$card->id}/withdrawals", $body);
}

/**
 * @param  array<model-property<CardWithdrawal>, mixed>  $over
 */
function wdAwaiting(Card $card, array $over = []): CardWithdrawal
{
    $w = new CardWithdrawal();
    $w->forceFill(array_merge([
        'user_id'                     => $card->user_id, 'card_id' => $card->id, 'provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC',
        'destination_address'         => WD_ADDRESS, 'destination_address_uuid' => (string) Str::uuid(), 'amount' => '50.00', 'status' => 'awaiting_code',
        'unloaded_cents'              => 5000, 'usdc_amount' => '49.650500', 'withdrawal_fee' => '1.000000', 'withdrawal_amount' => '49.650500',
        'provider_verification_token' => 'tok-secret-1', 'withdrawal_key' => (string) Str::uuid(), 'withdrawal_requested_at' => now(),
        'code_sent_at'                => now(), 'code_expires_at' => now()->addMinutes(10), 'code_sent_to' => 'a***@example.com',
        'estimate'                    => ['receive_amount' => '48.65'],
    ], $over))->save();

    return $w;
}

/**
 * @return Illuminate\Testing\TestResponse<Symfony\Component\HttpFoundation\Response>
 */
function wdConfirm(PHPUnit\Framework\TestCase $test, CardWithdrawal $w, string $code = 'AB12CD34', ?string $key = null): Illuminate\Testing\TestResponse
{
    assert($test instanceof Tests\TestCase);

    return $test->withHeader('Idempotency-Key', $key ?? (string) Str::uuid())->postJson("/api/v1/cards/issued/{$w->card_id}/withdrawals/{$w->id}/confirm", ['code' => $code]);
}

function wdCalls(string $suffix, string $method = 'POST'): int
{
    return collect(Http::recorded())->filter(fn (array $pair): bool => $pair[0]->method() === $method && str_ends_with((string) parse_url($pair[0]->url(), PHP_URL_PATH), $suffix))->count();
}

// ─── Switch, ownership and validation ──────────────────────────────────────

it('refuses everything while the switch is off', function (): void {
    config(['cardissuance.providers.lepo_withdrawals_enabled' => false]);
    $card = wdCard();
    Http::fake();

    wdStart($this, $card)->assertStatus(503)->assertJsonPath('error.code', 'ERR_CARDS_034');
    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals/estimate?amount=50.00&network=arbitrum")->assertStatus(503)->assertJsonPath('error.code', 'ERR_CARDS_034');
    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.withdrawals_enabled', false);
    Http::assertNothingSent();
});

it('refuses users outside the pilot while the pilot is on', function (): void {
    config(['cardissuance.providers.lepo_pilot_only' => true, 'cardissuance.providers.lepo_pilot_users' => 'someone@else.test']);
    $card = wdCard();
    Http::fake();

    wdStart($this, $card)->assertStatus(503)->assertJsonPath('error.code', 'ERR_CARDS_034');
    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.withdrawals_enabled', false);
});

it('shows withdrawals as available on the onboarding state', function (): void {
    wdCard();

    $this->getJson('/api/v1/cards/onboarding')->assertJsonPath('data.withdrawals_enabled', true);
});

it('hides other users cards and non-Lepo cards', function (): void {
    $theirs = wdCard();
    $mine = wdCard(token: '778', withAddress: false);
    $mine->forceFill(['issuer' => 'demo'])->save();
    Http::fake();

    wdStart($this, $theirs)->assertStatus(404)->assertJsonPath('error.code', 'ERR_CARDS_015');
    wdStart($this, $mine)->assertStatus(404)->assertJsonPath('error.code', 'ERR_CARDS_015');
    $this->getJson("/api/v1/cards/issued/{$theirs->id}/withdrawals")->assertStatus(404);
});

it('refuses a body that names an address', function (string $field): void {
    $card = wdCard();
    Http::fake();

    wdStart($this, $card, ['amount' => '50.00', 'network' => 'arbitrum', $field => '0x' . str_repeat('9', 40)])
        ->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_036');
    expect(CardWithdrawal::query()->count())->toBe(0);
})->with(['destination_address', 'address', 'to']);

it('refuses an unsupported network and a network without a chain code', function (): void {
    $card = wdCard();
    Http::fake();

    wdStart($this, $card, ['amount' => '50.00', 'network' => 'base'])->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_036');
    config(['cardissuance.providers.lepo_withdrawal_chain_codes' => 'polygon:MATIC']);
    wdStart($this, $card, ['amount' => '50.00', 'network' => 'arbitrum'])->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_036');
    Http::assertNothingSent();
});

it('validates amount, withdraw_all and close_card together', function (array $body): void {
    $card = wdCard();
    Http::fake();

    wdStart($this, $card, $body + ['network' => 'arbitrum'])->assertStatus(422);
})->with([
    'neither'                    => [[]],
    'both'                       => [['amount' => '50.00', 'withdraw_all' => true]],
    'three decimals'             => [['amount' => '50.001']],
    'exponent'                   => [['amount' => '5e1']],
    'close without withdraw_all' => [['amount' => '50.00', 'close_card' => true]],
]);

it('refuses a user without a smart-account address and ignores legacy rows', function (): void {
    $card = wdCard(withAddress: false);
    $user = $card->user()->firstOrFail();
    wdAddress($user, 'arbitrum', '0x2222222222222222222222222222222222222222', now()->subDays(5), ['provider' => 'custodial']);
    wdAddress($user, 'polygon', WD_ADDRESS, now()->subDays(5));
    Http::fake();

    wdStart($this, $card)->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_037');
});

it('refuses an address registered less than 24 hours ago', function (): void {
    $card = wdCard(withAddress: false);
    $row = wdAddress($card->user()->firstOrFail(), 'arbitrum', WD_ADDRESS, now()->subHours(2));
    Http::fake();

    wdStart($this, $card)->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_045')
        ->assertJsonPath('error.available_at', $row->created_at?->copy()->addHours(24)->toIso8601String());
});

it('refuses a card that cannot send money', function (string $status, ?string $token, bool $staff, string $reason): void {
    $card = wdCard($status, $token);
    if ($staff) {
        CardStaffFreeze::place('777', null, CardStaffFreeze::SOURCE_STAFF);
    }
    Http::fake();

    wdStart($this, $card)->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_038')->assertJsonPath('error.reason', $reason);
})->with([
    'staff freeze'     => ['frozen', '777', true, 'frozen_by_support'],
    'owner freeze'     => ['frozen', '777', false, 'frozen'],
    'cancelled'        => ['cancelled', '777', false, 'card_closed'],
    'payment required' => ['payment_required', '777', false, 'card_not_ready'],
    'never opened'     => ['pending', null, false, 'card_not_ready'],
]);

it('refuses while a top-up is open or unclear', function (string $status, ?string $reason): void {
    $card = wdCard();
    $topup = new CardTopUp();
    $topup->forceFill(['user_id' => $card->user_id, 'card_id' => $card->id, 'provider' => 'lepo', 'source' => 'app', 'asset' => 'USDC', 'network' => 'arbitrum',
        'amount'                 => '25', 'deposit_address' => '0xarb', 'status' => $status, 'last_error' => $reason, 'expires_at' => now()->addHour()])->save();
    Http::fake();

    wdStart($this, $card)->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_039')->assertJsonPath('error.kind', 'topup');
})->with([
    'waiting for a deposit' => ['awaiting_deposit', null],
    'unclear conversion'    => ['needs_attention', 'CONVERSION_UNCLEAR'],
]);

it('refuses a top-up while a withdrawal is active', function (): void {
    $card = wdCard();
    $card->forceFill(['funding_account_id' => $card->funding_account_id])->save();
    CardFundingAccount::query()->whereKey($card->funding_account_id)->firstOrFail()
        ->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0x' . str_repeat('a', 40), 'is_active' => true]);
    wdAwaiting($card);
    Http::fake(['lepo.test/api/v2/cards/quantum-topup/estimate*' => Http::response(['success' => true, 'topupfee' => '0', 'topupfeepercent' => '0.015',
        'usdcToUsd'                                                                           => ['rate' => '0.999', 'fee' => '0.05', 'feeCurrency' => 'USD']])]);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$card->id}/topups", ['amount' => '25.00', 'network' => 'arbitrum'])
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_031');
});

it('refuses an amount outside the limits', function (string $amount): void {
    $card = wdCard();
    wdLepo(['available' => '2000.00']);

    wdStart($this, $card, ['amount' => $amount, 'network' => 'arbitrum'])->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_035')
        ->assertJsonPath('error.min_amount', '5.00')->assertJsonPath('error.max_amount', '1000.00');
})->with(['4.99', '1000.01']);

it('refuses an amount above the live card balance', function (): void {
    $card = wdCard();
    wdLepo(['available' => '40.00']);

    wdStart($this, $card)->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_040')->assertJsonPath('error.available_cents', 4000);
});

it('answers provider unavailable when the card balance cannot be read', function (): void {
    $card = wdCard();
    Http::fake(['lepo.test/*' => Http::response(['message' => 'down'], 503)]);

    wdStart($this, $card)->assertStatus(502)->assertJsonPath('error.code', 'ERR_CARDS_016');
});

it('starts a withdrawal to the pinned address and queues the job', function (): void {
    $card = wdCard();
    wdLepo();

    $response = wdStart($this, $card)->assertCreated()
        ->assertJsonPath('data.status', 'unloading')
        ->assertJsonPath('data.card_id', $card->id)
        ->assertJsonPath('data.amount', '50.00')
        ->assertJsonPath('data.withdraw_all', false)
        ->assertJsonPath('data.close_card', false)
        ->assertJsonPath('data.network', 'arbitrum')
        ->assertJsonPath('data.asset', 'USDC')
        ->assertJsonPath('data.destination_address', WD_ADDRESS)
        ->assertJsonPath('data.estimated_receive_amount', '48.65')
        ->assertJsonPath('data.unloaded_cents', null)
        ->assertJsonPath('data.code_sent_to', null)
        ->assertJsonPath('data.card_closed', false)
        ->assertJsonPath('data.failure_reason', null);

    expect(array_keys((array) $response->json('data')))->toBe([
        'id', 'card_id', 'status', 'amount', 'withdraw_all', 'close_card', 'network', 'asset', 'destination_address', 'unloaded_cents', 'unload_fee_cents',
        'conversion_fee_cents', 'withdrawal_fee', 'received_amount', 'estimated_receive_amount', 'tx_hash', 'code_sent_to', 'code_expires_at',
        'code_attempts_left', 'card_closed', 'failure_reason', 'created_at', 'completed_at',
    ]);

    $w = CardWithdrawal::query()->sole();
    $row = BlockchainAddress::query()->where('chain', 'arbitrum')->sole();
    expect($w->destination_address_uuid)->toBe($row->uuid)->and($w->unload_key)->toBeNull()
        ->and(CardWithdrawalEvent::query()->where('withdrawal_id', $w->id)->where('action', 'started')->value('ip_address'))->toBe('127.0.0.1');
    Bus::assertDispatched(AdvanceLepoWithdrawals::class, fn (AdvanceLepoWithdrawals $job): bool => $job->lepoUserId === '12345');
    // Only reads: the card balance, the quote and the fee. No money moves in the request.
    expect(wdCalls('/cards/777', 'GET'))->toBe(1)->and(collect(Http::recorded())->filter(fn (array $p): bool => $p[0]->method() === 'POST')->count())->toBe(0);
    Event::assertDispatched(CardWithdrawalUpdated::class, fn (CardWithdrawalUpdated $e): bool => $e->status === 'unloading');
});

it('picks the most recently registered address and shows it in the estimate', function (): void {
    $card = wdCard();
    $newer = '0x3333333333333333333333333333333333333333';
    wdAddress($card->user()->firstOrFail(), 'arbitrum', $newer, now()->subDay()->subHour());
    wdLepo();

    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals/estimate?amount=50.00&network=arbitrum")->assertOk()->assertJsonPath('data.destination_address', $newer);
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'fee-and-quota') && str_contains($r->url(), 'address=' . $newer) && str_contains($r->url(), 'chain=ARB'));
});

it('takes the live balance with withdraw_all and accepts close_card', function (): void {
    $card = wdCard();
    wdLepo(['available' => '62.109']);

    wdStart($this, $card, ['withdraw_all' => true, 'close_card' => true, 'network' => 'arbitrum'])->assertCreated()
        ->assertJsonPath('data.amount', '62.10')->assertJsonPath('data.withdraw_all', true)->assertJsonPath('data.close_card', true);
});

it('lets the remaining balance go below the minimum when it covers the exchange minimum and fees', function (): void {
    $card = wdCard();
    wdLepo(['available' => '3.00']);

    // 3.00 - 0.25 = 2.75 x 0.998 = 2.7445 USDC; less the 1.00 fee = 1.74 to the wallet.
    wdStart($this, $card, ['withdraw_all' => true, 'network' => 'arbitrum'])->assertCreated()->assertJsonPath('data.estimated_receive_amount', '1.74');
});

it('refuses a remaining balance that does not cover the exchange minimum and fees', function (): void {
    $card = wdCard();
    wdLepo(['available' => '1.20']);

    // 0.50 exchange minimum + 0.25 conversion fee + 1.00 withdrawal fee + 0.01 = 1.76.
    wdStart($this, $card, ['withdraw_all' => true, 'network' => 'arbitrum'])->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_035')
        ->assertJsonPath('error.min_amount', '1.76');
});

it('refuses a second withdrawal while one is active', function (): void {
    $card = wdCard();
    wdLepo();

    wdStart($this, $card)->assertCreated();
    wdStart($this, $card)->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_039')->assertJsonPath('error.kind', 'withdrawal');
    expect(CardWithdrawal::query()->count())->toBe(1);
});

it('replays the same Idempotency-Key without a second withdrawal', function (): void {
    $card = wdCard();
    wdLepo();
    $key = (string) Str::uuid();

    $first = wdStart($this, $card, key: $key)->assertCreated();
    wdStart($this, $card, key: $key)->assertCreated()->assertJsonPath('data.id', $first->json('data.id'));
    wdStart($this, $card, ['amount' => '60.00', 'network' => 'arbitrum'], $key)->assertStatus(409)->assertJsonPath('error.code', 'ERR_IDEMPOTENCY_409');
    expect(CardWithdrawal::query()->count())->toBe(1);
});

it('refuses when the daily count or amount is reached, also after a cache flush', function (): void {
    $card = wdCard();
    wdLepo(['available' => '2000.00']);
    foreach ([1, 2, 3] as $i) {
        wdAwaiting($card, ['status' => 'completed', 'amount' => '10.00']);
    }

    wdStart($this, $card)->assertStatus(429)->assertJsonPath('error.code', 'ERR_CARDS_044')->assertJsonPath('error.daily_limit', '2000.00');
    Cache::flush();
    wdStart($this, $card)->assertStatus(429)->assertJsonPath('error.code', 'ERR_CARDS_044');

    CardWithdrawal::query()->delete();
    wdAwaiting($card, ['status' => 'completed', 'amount' => '1000.00']);
    wdAwaiting($card, ['status' => 'completed', 'amount' => '980.00']);
    wdStart($this, $card, ['amount' => '25.00', 'network' => 'arbitrum'])->assertStatus(429)->assertJsonPath('error.code', 'ERR_CARDS_044')
        ->assertJsonPath('error.daily_remaining', '20.00');
});

it('does not count a cancelled withdrawal against the daily limits', function (): void {
    $card = wdCard();
    wdLepo();
    foreach ([1, 2, 3] as $i) {
        wdAwaiting($card, ['status' => 'cancelled']);
    }

    wdStart($this, $card)->assertCreated();
});

// ─── Estimate ──────────────────────────────────────────────────────────────

it('estimates with a fee in US dollars, to the cent', function (): void {
    // Set the clock before the card exists: wdCard() dates the address two
    // days before "now", and moving the clock back afterwards made it look new.
    $this->travelTo(Carbon\Carbon::parse('2026-11-25T12:00:00Z'));
    $card = wdCard();
    wdLepo();

    // (50.00 - 0.25) x 0.998 = 49.6505 USDC; less the 1.00 network fee = 48.6505, shown as 48.65.
    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals/estimate?amount=50.00&network=arbitrum")->assertOk()->assertJsonPath('data', [
        'amount'          => '50.00', 'withdraw_all' => false, 'network' => 'arbitrum', 'asset' => 'USDC', 'destination_address' => WD_ADDRESS,
        'available_cents' => 6210, 'unload_fee_cents' => 0, 'conversion_fee_cents' => 25, 'withdrawal_fee' => '1.00', 'receive_amount' => '48.65',
        'min_amount'      => '5.00', 'max_amount' => '1000.00', 'daily_remaining' => '2000.00', 'quote_expires_at' => '2026-11-25T12:01:00+00:00',
    ]);
});

it('estimates with a fee in USDC', function (): void {
    $card = wdCard();
    wdLepo(['fee_currency' => 'USDC']);

    // 50.00 x 0.998 = 49.90 USDC less 0.25 USDC = 49.65; the fee is 0.2505 USD, shown as 26 cents.
    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals/estimate?amount=50.00&network=arbitrum")
        ->assertJsonPath('data.receive_amount', '48.65')->assertJsonPath('data.conversion_fee_cents', 26);
});

it('rounds what the user receives down and every fee up', function (): void {
    $card = wdCard();
    wdLepo(['rate' => '0.99999', 'fee' => '0.333', 'withdrawal_fee' => '0.1234567']);

    // 0.333 -> 0.34; (50 - 0.34) x 0.99999 = 49.6595034 -> 49.659503; fee 0.1234567 -> 0.123457; 49.536046 -> 49.53.
    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals/estimate?amount=50.00&network=arbitrum")
        ->assertJsonPath('data.conversion_fee_cents', 34)->assertJsonPath('data.withdrawal_fee', '0.13')->assertJsonPath('data.receive_amount', '49.53');
});

it('estimates with the live balance for all=1', function (): void {
    $card = wdCard();
    wdLepo(['available' => '30.00']);

    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals/estimate?all=1&network=arbitrum")
        ->assertOk()->assertJsonPath('data.amount', '30.00')->assertJsonPath('data.withdraw_all', true);
});

it('answers 035 when the fees eat the whole amount', function (): void {
    $card = wdCard();
    wdLepo(['withdrawal_fee' => '60.00']);

    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals/estimate?amount=50.00&network=arbitrum")
        ->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_035')->assertJsonPath('error.min_amount', '60.01');
});

// ─── The emailed code ──────────────────────────────────────────────────────

it('confirms with the right code and moves to sending', function (int $status): void {
    $card = wdCard();
    $w = wdAwaiting($card);
    wdLepo(['confirm' => Http::response(['success' => true, 'transactionId' => 'wd-tx-1', 'interlaceTransferId' => 'il-1', 'status' => 'PROCESSING', 'actualAmount' => 48.65, 'otpRequired' => true], $status)]);

    wdConfirm($this, $w, ' AB12 CD34 ')->assertOk()->assertJsonPath('data.status', 'sending')->assertJsonPath('data.failure_reason', null);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/withdrawals/crypto/confirm')
        && $r['verificationToken'] === 'tok-secret-1' && $r['otpCode'] === 'AB12CD34' && $r['userId'] === 12345
        && ! array_key_exists('withdrawalRequestId', $r->data()) && $r->hasHeader('Idempotency-Key'));
    $w->refresh();
    expect($w->provider_withdrawal_id)->toBe('wd-tx-1')->and($w->provider_transfer_id)->toBe('il-1')->and($w->provider_actual_amount)->toBe('48.650000')
        ->and($w->confirmed_at)->not->toBeNull()->and($w->confirm_key)->not->toBeNull();
    Event::assertDispatched(CardWithdrawalUpdated::class, fn (CardWithdrawalUpdated $e): bool => $e->status === 'sending');
})->with(['ok' => [200], 'accepted' => [202]]);

it('refuses a code that is not 8 characters without asking the provider', function (string $code): void {
    $card = wdCard();
    $w = wdAwaiting($card);
    Http::fake();

    wdConfirm($this, $w, $code)->assertStatus(422)->assertJsonPath('error.code', 'ERR_CARDS_041')->assertJsonPath('error.code_attempts_left', null);
    Http::assertNothingSent();
    expect($w->refresh()->code_attempts)->toBe(0);
})->with(['ABC123', 'AB12CD345']);

it('maps the provider wrong-code answer in order: locked, expired, wrong', function (int $http, array $body, int $expect, string $code, string $status): void {
    $card = wdCard();
    $w = wdAwaiting($card);
    wdLepo(['confirm' => Http::response($body + ['message' => 'Invalid code', 'code' => 'OTP_INVALID'], $http)]);

    $response = wdConfirm($this, $w)->assertStatus($expect)->assertJsonPath('error.code', $code);
    expect($w->refresh()->status)->toBe($status);
    if ($code === 'ERR_CARDS_041') {
        $response->assertJsonPath('error.code_attempts_left', 2);
        expect($w->code_attempts)->toBe(1)->and($w->code_attempts_left)->toBe(2);
    }
    if ($code === 'ERR_CARDS_043') {
        expect($w->last_error)->toBe('CODE_LOCKED');
    }
})->with([
    'locked wins over expired (400)' => [400, ['remainingAttempts' => 0, 'isExpired' => true, 'isLocked' => true], 409, 'ERR_CARDS_043', 'needs_attention'],
    'expired (404)'                  => [404, ['remainingAttempts' => 2, 'isExpired' => true, 'isLocked' => false], 410, 'ERR_CARDS_042', 'awaiting_code'],
    'wrong (400)'                    => [400, ['remainingAttempts' => 2, 'isExpired' => false, 'isLocked' => false], 422, 'ERR_CARDS_041', 'awaiting_code'],
    'wrong (404)'                    => [404, ['remainingAttempts' => 2, 'isExpired' => false, 'isLocked' => false], 422, 'ERR_CARDS_041', 'awaiting_code'],
]);

it('answers 202 with checking when the confirm call times out, and never confirms again', function (): void {
    $card = wdCard();
    $w = wdAwaiting($card);
    wdLepo(['confirm' => Http::response(['message' => 'gateway timeout'], 504)]);

    wdConfirm($this, $w)->assertStatus(202)->assertJsonPath('data.status', 'sending')->assertJsonPath('data.failure_reason', 'checking');
    wdConfirm($this, $w)->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_046');
    expect(wdCalls('/withdrawals/crypto/confirm'))->toBe(1)->and($w->refresh()->last_error)->toBe('CONFIRM_CHECKING');
});

it('replays a confirm with the same key without asking the provider again', function (): void {
    $card = wdCard();
    $w = wdAwaiting($card);
    wdLepo();
    $key = (string) Str::uuid();

    wdConfirm($this, $w, key: $key)->assertOk();
    wdConfirm($this, $w, key: $key)->assertOk()->assertJsonPath('data.status', 'sending');
    expect(wdCalls('/withdrawals/crypto/confirm'))->toBe(1);
});

it('refuses a confirm when the withdrawal is not waiting for a code', function (): void {
    $card = wdCard();
    $w = wdAwaiting($card, ['status' => 'converting']);
    Http::fake();

    wdConfirm($this, $w)->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_046');
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$card->id}/withdrawals/{$w->id}/resend-code")
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_046');
});

it('limits confirm calls to 5 per withdrawal in 10 minutes', function (): void {
    $card = wdCard();
    $w = wdAwaiting($card);
    Http::fake();

    foreach (range(1, 5) as $i) {
        wdConfirm($this, $w, 'SHORT')->assertStatus(422);
    }
    wdConfirm($this, $w, 'SHORT')->assertStatus(429)->assertJsonPath('error.code', 'ERR_CARDS_047');
});

it('resends the code once per minute and at most 5 times', function (): void {
    $this->freezeTime();
    $card = wdCard();
    $w = wdAwaiting($card, ['code_sent_at' => now()->subMinutes(5)]);
    wdLepo();
    $resend = fn () => $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$card->id}/withdrawals/{$w->id}/resend-code");

    $resend()->assertOk()->assertJsonPath('data.code_expires_at', '2030-01-01T10:20:00+00:00');
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/crypto/resend') && $r['verificationToken'] === 'tok-secret-1' && $r['userId'] === 12345);
    $resend()->assertStatus(429)->assertJsonPath('error.code', 'ERR_CARDS_047')->assertJsonPath('error.retry_after_seconds', 60);

    foreach (range(2, 5) as $i) {
        $this->travel(61)->seconds();
        $resend()->assertOk();
    }
    $this->travel(61)->seconds();
    $resend()->assertStatus(429)->assertJsonPath('error.code', 'ERR_CARDS_047');
    expect($w->refresh()->code_resends)->toBe(5);
    Event::assertDispatched(CardWithdrawalUpdated::class, fn (CardWithdrawalUpdated $e): bool => $e->codeExpiresAt === '2030-01-01T10:20:00+00:00');
});

it('stops for an operator when the resend is refused because the request expired', function (): void {
    $card = wdCard();
    $w = wdAwaiting($card, ['code_sent_at' => now()->subMinutes(5)]);
    wdLepo(['resend' => Http::response(['message' => 'Withdrawal request expired', 'code' => 'REQUEST_EXPIRED'], 404)]);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$card->id}/withdrawals/{$w->id}/resend-code")
        ->assertOk()->assertJsonPath('data.status', 'needs_attention')->assertJsonPath('data.failure_reason', 'checking');
    expect($w->refresh()->last_error)->toBe('REQUEST_EXPIRED')->and(wdCalls('/transfers/withdrawals/crypto'))->toBe(0);
});

it('lets in-flight withdrawals finish while the switch is off', function (): void {
    config(['cardissuance.providers.lepo_withdrawals_enabled' => false]);
    $card = wdCard();
    $w = wdAwaiting($card);
    wdLepo();

    wdConfirm($this, $w)->assertOk()->assertJsonPath('data.status', 'sending');
    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals/{$w->id}")->assertOk();
    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals")->assertOk()->assertJsonPath('data.items.0.id', $w->id);
});

it('lists the card withdrawals newest first in pages', function (): void {
    $card = wdCard();
    foreach (range(1, 21) as $i) {
        $this->travel(1)->minutes();
        wdAwaiting($card, ['status' => 'completed']);
    }

    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals")->assertOk()->assertJsonCount(20, 'data.items')
        ->assertJsonPath('data.has_more', true)->assertJsonPath('data.page', 0);
    $this->getJson("/api/v1/cards/issued/{$card->id}/withdrawals?page=1")->assertJsonCount(1, 'data.items')->assertJsonPath('data.has_more', false);
});

it('never keeps or shows the code or the token', function (): void {
    $card = wdCard();
    $w = wdAwaiting($card);
    // One fake with a sequence: stubs accumulate and the first match wins.
    wdLepo(['confirm' => Http::sequence()
        ->push(['remainingAttempts' => 2, 'isExpired' => false, 'isLocked' => false, 'message' => 'Invalid'], 400)
        ->push(['success' => true, 'transactionId' => 'wd-tx-1', 'status' => 'PROCESSING', 'actualAmount' => 48.65])]);
    $logged = [];
    Log::listen(function (Illuminate\Log\Events\MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message . json_encode($event->context);
    });

    $response = wdConfirm($this, $w, 'ZQ98XW76')->assertStatus(422)->assertJsonPath('error.code_attempts_left', 2);
    $ok = wdConfirm($this, $w, 'GOOD5678')->assertOk();

    foreach ([$response->getContent(), $ok->getContent()] as $body) {
        expect($body)->not->toContain('ZQ98XW76')->not->toContain('GOOD5678')->not->toContain('tok-secret-1');
    }
    $dump = json_encode([
        DB::table('card_withdrawals')->get(), DB::table('card_withdrawal_events')->get(), DB::table('idempotency_keys')->get(['response_body']),
    ]);
    expect($dump)->not->toContain('ZQ98XW76')->not->toContain('GOOD5678')->not->toContain('tok-secret-1');
    expect(implode("\n", $logged))->not->toContain('GOOD5678')->not->toContain('ZQ98XW76')->not->toContain('tok-secret-1');
    Event::assertDispatched(CardWithdrawalUpdated::class, fn (CardWithdrawalUpdated $e): bool => ! str_contains((string) json_encode($e->broadcastWith()), 'GOOD5678'));
});

it('treats an unexpected error during the confirm call as unclear', function (): void {
    $card = wdCard();
    $w = wdAwaiting($card);
    Http::fake(fn () => throw new RuntimeException('unexpected'));

    wdConfirm($this, $w)->assertStatus(202)->assertJsonPath('data.failure_reason', 'checking');
    expect($w->refresh()->status)->toBe('sending')->and($w->last_error)->toBe('CONFIRM_CHECKING');
});

it('keeps the code out of the error trace of a failed request', function (): void {
    $logged = [];
    Log::listen(function (Illuminate\Log\Events\MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message . json_encode($event->context);
    });
    $request = Illuminate\Http\Request::create('/api/v1/cards/issued/x/withdrawals/y/confirm', 'POST', ['code' => 'LEAK1234', 'note' => 'kept']);

    expect(fn () => app(App\Http\Middleware\TracingMiddleware::class)->handle($request, fn () => throw new RuntimeException('boom')))->toThrow(RuntimeException::class);
    $log = implode("\n", $logged);
    expect($log)->toContain('kept');
    expect(str_contains($log, 'LEAK1234'))->toBeFalse();
});

it('keeps the confirm request out of Telescope', function (): void {
    expect(config('telescope.ignore_paths'))->toContain('api/v1/cards/issued/*/withdrawals/*/confirm');
});
