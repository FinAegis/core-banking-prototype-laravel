<?php

declare(strict_types=1);

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Account\Models\BlockchainTransaction;
use App\Domain\CardIssuance\Events\Broadcast\CardWithdrawalUpdated;
use App\Domain\CardIssuance\Jobs\AdvanceLepoTopUps;
use App\Domain\CardIssuance\Jobs\AdvanceLepoWithdrawals;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardStaffFreeze;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Models\CardWithdrawalEvent;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUpPipeline;
use App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawalPipeline;
use App\Domain\CardIssuance\Webhooks\LepoWebhookRouter;
use App\Domain\Mobile\Services\PushNotificationService;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

const PW_ADDRESS = '0x4444444444444444444444444444444444444444';

beforeEach(function (): void {
    config([
        'cache.default'                                      => 'array',
        'cardissuance.issuers.lepo.base_url'                 => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'                  => 'k',
        'cardissuance.providers.lepo_withdrawals_enabled'    => true,
        'cardissuance.providers.lepo_withdrawal_networks'    => 'arbitrum,polygon',
        'cardissuance.providers.lepo_withdrawal_chain_codes' => 'arbitrum:ARB,polygon:MATIC',
        'cardissuance.providers.lepo_topups_enabled'         => true,
    ]);
    Cache::lock(LepoTopUpPipeline::CONVERSION_SLOT_KEY)->forceRelease();
    Http::preventStrayRequests();
    Event::fake([CardWithdrawalUpdated::class]);
    $this->push = Mockery::mock(PushNotificationService::class);
    $this->push->shouldReceive('sendToUser')->andReturn([])->byDefault();
    app()->instance(PushNotificationService::class, $this->push);
});

/**
 * @param  array<model-property<CardWithdrawal>, mixed>  $over
 */
function pwWithdrawal(array $over = [], string $cardStatus = 'active', string $token = '777'): CardWithdrawal
{
    $user = User::factory()->create();
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345']);
    $holder->save();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'provider' => 'lepo', 'status' => 'active']);
    $card = Card::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'funding_account_id' => $account->id, 'issuer' => 'lepo', 'status' => $cardStatus,
        'issuer_card_token'         => $token, 'last4' => '1234', 'network' => 'visa', 'balance_cents' => 6210]);
    $pinned = $token === '777' ? PW_ADDRESS : '0x' . str_repeat('9', 40);
    $address = BlockchainAddress::create(['user_uuid' => $user->uuid, 'chain' => 'arbitrum', 'address' => $pinned, 'public_key' => $pinned, 'is_active' => true,
        'metadata'                                    => ['provider' => 'privy', 'wallet_kind' => 'privy_smart_account']]);

    $w = new CardWithdrawal();
    $w->forceFill(array_merge([
        'user_id'             => $user->id, 'card_id' => $card->id, 'provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC',
        'destination_address' => PW_ADDRESS, 'destination_address_uuid' => $address->uuid, 'amount' => '50.00', 'status' => 'unloading',
        'estimate'            => ['receive_amount' => '48.65'],
    ], $over))->save();

    return $w;
}

/**
 * Lepo, answering each step. A list of responses for a key is answered in
 * order, the last one repeating.
 *
 * @param  array<string, mixed>  $over
 */
function pwLepo(array $over = []): void
{
    $next = function (string $key) use (&$over) {
        if (! isset($over[$key]) || ! is_array($over[$key])) {
            return $over[$key] ?? null;
        }

        return count($over[$key]) > 1 ? array_shift($over[$key]) : $over[$key][0];
    };
    Http::fake(function (Request $r) use ($next) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);
        $post = $r->method() === 'POST';
        if ($post && str_ends_with($path, '/cards/unload-card')) {
            return $next('unload') ?? Http::response(['success' => true, 'cardId' => 777, 'requestedAmount' => 50.0, 'actualAmount' => 50.0, 'fee' => 0, 'netAmount' => 50.0,
                'currency'                                      => 'USD', 'status' => 'COMPLETED', 'transactionId' => 'un-1', 'balanceAfter' => ['availableBalance' => 12.1, 'pendingBalance' => 0, 'totalBalance' => 12.1]]);
        }
        if ($post && str_ends_with($path, '/transfers/quantum-usd-to-crypto-exchange')) {
            return $next('exchange') ?? Http::response(['success' => true, 'sourceAmount' => 50.0, 'targetCurrency' => 'USDC', 'cryptoAmountReceived' => 49.6505, 'rate' => 0.998, 'fee' => 0.25, 'tradeId' => 'tr-9', 'status' => 'COMPLETED']);
        }
        if ($post && str_ends_with($path, '/transfers/withdrawals/crypto')) {
            return $next('request') ?? Http::response(['success' => true, 'verificationToken' => 'tok-1', 'transactionId' => 'wd-1', 'status' => 'PENDING_OTP', 'actualAmount' => 48.6505,
                'currency'                                       => 'USDC', 'fees' => [['amount' => '1.00', 'currency' => 'USDC', 'type' => 'network']], 'otpRequired' => true, 'otpExpiresAt' => '2030-01-01T10:10:00Z'], 201);
        }
        if (str_ends_with($path, '/transfers/withdrawals/fee-and-quota')) {
            return $next('fee') ?? Http::response(['code' => '0', 'data' => ['fees' => [['amount' => '1.000000', 'currency' => 'USDC', 'type' => 'network']]]]);
        }
        if (str_ends_with($path, '/cards/quantum-topup/estimate')) {
            return Http::response(['success' => true, 'topupfee' => '0', 'topupfeepercent' => '0.015', 'usdcToUsd' => ['rate' => '0.999', 'fee' => '0.05', 'feeCurrency' => 'USD']]);
        }
        if (str_ends_with($path, '/transactions')) {
            return $next('transactions') ?? Http::response(['data' => [], 'pagination' => ['page' => 1, 'hasNext' => false]]);
        }
        if (str_ends_with($path, '/transfers/crypto-transactions')) {
            return $next('crypto') ?? Http::response(['transfers' => [], 'total' => 0]);
        }
        if (str_ends_with($path, '/users/12345/assets')) {
            return $next('assets') ?? Http::response(['assets' => [['assetType' => 'crypto', 'balanceType' => 'Wallet', 'balanceId' => 'b-usdc', 'currency' => 'USDC', 'balance' => '0', 'availableBalance' => '0']]]);
        }
        if (str_ends_with($path, '/users/12345')) {
            return Http::response(['id' => 12345, 'email' => 'ada@example.com']);
        }
        if ($r->method() === 'DELETE' && str_ends_with($path, '/cards/777')) {
            return Http::response(['success' => true]);
        }
        if (str_ends_with($path, '/cards/777')) {
            return $next('card') ?? Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '62.10', 'pending' => '0', 'frozen' => '0']]);
        }

        return Http::response(['message' => 'unexpected ' . $path], 500);
    });
}

function pwCalls(string $suffix, string $method = 'POST'): int
{
    return collect(Http::recorded())->filter(fn (array $pair): bool => $pair[0]->method() === $method && str_ends_with((string) parse_url($pair[0]->url(), PHP_URL_PATH), $suffix))->count();
}

function pwRun(CardWithdrawal $w): CardWithdrawal
{
    (new AdvanceLepoWithdrawals('12345'))->handle();

    return $w->refresh();
}

it('runs unload, exchange and withdrawal request in order and waits for the code', function (): void {
    $w = pwWithdrawal();
    pwLepo();
    $this->push->shouldReceive('sendToUser')->once()->withArgs(fn ($user, string $type, string $title, string $body, array $data): bool => $data === ['type' => 'card.withdrawal.updated', 'withdrawal_id' => $w->id, 'card_id' => $w->card_id]
        && str_contains($body, 'Check your email'))->andReturn([]);

    $w = pwRun($w);

    expect($w->status)->toBe('awaiting_code')
        ->and($w->unloaded_cents)->toBe(5000)->and($w->unload_fee_cents)->toBe(0)->and($w->provider_unload_id)->toBe('un-1')
        ->and($w->usdc_amount)->toBe('49.650500')->and($w->conversion_fee_cents)->toBe(25)->and($w->provider_trade_id)->toBe('tr-9')
        ->and($w->withdrawal_fee)->toBe('1.000000')->and($w->withdrawal_amount)->toBe('49.650500')
        ->and($w->provider_verification_token)->toBe('tok-1')->and($w->code_sent_to)->toBe('a***@example.com')
        ->and($w->code_expires_at?->toIso8601String())->toBe('2030-01-01T10:10:00+00:00');
    expect(pwCalls('/cards/unload-card'))->toBe(1)->and(pwCalls('/quantum-usd-to-crypto-exchange'))->toBe(1)->and(pwCalls('/transfers/withdrawals/crypto'))->toBe(1);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/cards/unload-card') && $r['cardId'] === '777' && $r['value'] === '50.00' && $r['currency'] === 'USD'
        && $r->header('Idempotency-Key')[0] === $w->unload_key);
    // Exactly the unloaded dollars, never the whole USD balance.
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'quantum-usd-to-crypto-exchange?userId=12345') && $r['amount'] === '50.00' && $r['currency'] === 'USDC'
        && $r->header('Idempotency-Key')[0] === $w->exchange_key);
    // Exactly the exchanged USDC, to the pinned address.
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/transfers/withdrawals/crypto') && $r['amount'] === '49.650500' && $r['destinationAddress'] === PW_ADDRESS
        && $r['chain'] === 'ARB' && $r['currency'] === 'USDC' && $r['confirmExchangeRate'] === false && $r['userId'] === 12345 && $r->header('Idempotency-Key')[0] === $w->withdrawal_key);
    expect(array_unique([$w->unload_key, $w->exchange_key, $w->withdrawal_key]))->toHaveCount(3);

    Event::assertDispatched(CardWithdrawalUpdated::class, fn (CardWithdrawalUpdated $e): bool => $e->broadcastWith() === [
        'withdrawal_id' => $w->id, 'card_id' => $w->card_id, 'status' => 'awaiting_code', 'received_amount' => null, 'code_expires_at' => '2030-01-01T10:10:00+00:00',
    ] && $e->broadcastAs() === 'card.withdrawal.updated' && $e->broadcastOn()[0]->name === "private-user.{$w->user_id}");
    expect(CardWithdrawalEvent::query()->where('withdrawal_id', $w->id)->pluck('action')->all())
        ->toContain('call_started', 'call_finished', 'status_changed', 'code_sent');
});

it('stops in needs_attention when the unload times out and the reads find nothing, and never unloads twice', function (): void {
    $w = pwWithdrawal();
    pwLepo(['unload' => Http::response(['message' => 'timeout'], 504)]);

    expect(pwRun($w)->status)->toBe('unloading')->and($w->last_error)->toBe('UNLOAD_CHECKING');
    $this->travel(16)->minutes();
    expect(pwRun($w)->status)->toBe('needs_attention')->and($w->last_error)->toBe('UNLOAD_UNCLEAR');
    expect(pwCalls('/cards/unload-card'))->toBe(1);
});

it('moves on when the unload timed out but the card_unload record shows it', function (): void {
    $w = pwWithdrawal();
    pwLepo([
        'unload'       => Http::response(['message' => 'timeout'], 504),
        'transactions' => Http::response(['data' => [['id' => 501, 'type' => 'card_unload', 'amount' => -50.0, 'currency' => 'USD', 'status' => 'completed', 'cardId' => 777]]]),
    ]);

    $w = pwRun($w);

    expect($w->status)->toBe('awaiting_code')->and($w->unloaded_cents)->toBe(5000)->and($w->provider_unload_id)->toBe('501');
    expect(pwCalls('/cards/unload-card'))->toBe(1);
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), '/transactions?') && str_contains($r->url(), 'types=card_unload'));
});

it('moves on when the unload timed out but the card balance fell by exactly the amount', function (): void {
    $w = pwWithdrawal();
    pwLepo([
        'unload' => Http::response(['message' => 'timeout'], 504),
        'card'   => [
            Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '62.10', 'pending' => '0', 'frozen' => '0']]),
            Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '12.10', 'pending' => '0', 'frozen' => '0']]),
        ],
    ]);

    expect(pwRun($w)->status)->toBe('awaiting_code');
});

it('cancels when the unload is refused and the balance is unchanged, and stops when it changed', function (string $after, string $status): void {
    $w = pwWithdrawal();
    pwLepo([
        'unload' => Http::response(['message' => 'refused', 'errorCode' => 'CARD_LIMIT'], 400),
        'card'   => [
            Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '62.10']]),
            Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => $after]]),
        ],
    ]);

    expect(pwRun($w)->status)->toBe($status);
    expect(pwCalls('/cards/unload-card'))->toBe(1)->and(pwCalls('/quantum-usd-to-crypto-exchange'))->toBe(0);
})->with([
    'unchanged' => ['62.10', 'cancelled'],
    'changed'   => ['30.00', 'needs_attention'],
]);

it('stops on an unload amount mismatch', function (): void {
    $w = pwWithdrawal();
    pwLepo(['unload' => Http::response(['success' => true, 'requestedAmount' => 40.0, 'netAmount' => 40.0, 'fee' => 0])]);

    expect(pwRun($w)->status)->toBe('needs_attention')->and($w->last_error)->toBe('UNLOAD_AMOUNT_MISMATCH');
    expect(pwCalls('/quantum-usd-to-crypto-exchange'))->toBe(0);
});

it('carries a lower netAmount forward and records the fee and pending balance', function (): void {
    $w = pwWithdrawal(['withdraw_all' => true, 'close_card' => true]);
    pwLepo([
        'unload'   => Http::response(['success' => true, 'requestedAmount' => 50.0, 'netAmount' => 49.5, 'fee' => 0.5, 'transactionId' => 'un-2', 'balanceAfter' => ['availableBalance' => 0, 'pendingBalance' => 3.2]]),
        'exchange' => Http::response(['success' => true, 'sourceAmount' => 49.5, 'cryptoAmountReceived' => 49.15, 'fee' => 0.25, 'tradeId' => 'tr-2']),
    ]);

    $w = pwRun($w);

    expect($w->unloaded_cents)->toBe(4950)->and($w->unload_fee_cents)->toBe(50)->and($w->card_closed)->toBeFalse();
    Http::assertSent(fn (Request $r): bool => str_contains($r->url(), 'quantum-usd-to-crypto-exchange') && $r['amount'] === '49.50');
    expect(CardWithdrawalEvent::query()->where('withdrawal_id', $w->id)->pluck('note')->filter()->implode('|'))->toContain('pending payments')->toContain('unloaded 49.50 of 50.00');
});

it('waits for the shared exchange slot and treats a 429 as not done', function (): void {
    $w = pwWithdrawal(['status' => 'converting', 'unloaded_cents' => 5000, 'unloaded_at' => now()]);
    pwLepo(['exchange' => [Http::response(['message' => 'too many'], 429), Http::response(['success' => true, 'sourceAmount' => 50.0, 'cryptoAmountReceived' => 49.6505, 'fee' => 0.25, 'tradeId' => 'tr-9'])]]);

    Cache::lock(LepoTopUpPipeline::CONVERSION_SLOT_KEY, 15)->get();
    expect(pwRun($w)->status)->toBe('converting')->and($w->last_error)->toBe('EXCHANGE_QUEUED');
    expect(pwCalls('/quantum-usd-to-crypto-exchange'))->toBe(0);

    Cache::lock(LepoTopUpPipeline::CONVERSION_SLOT_KEY)->forceRelease();
    expect(pwRun($w)->status)->toBe('converting')->and($w->last_error)->toBe('EXCHANGE_RATE_LIMITED')->and($w->step_started_at)->toBeNull();

    Cache::lock(LepoTopUpPipeline::CONVERSION_SLOT_KEY)->forceRelease();
    expect(pwRun($w)->status)->toBe('awaiting_code');
    expect(pwCalls('/quantum-usd-to-crypto-exchange'))->toBe(2);
});

it('stops in needs_attention when the exchange times out and the reads find nothing', function (): void {
    $w = pwWithdrawal(['status' => 'converting', 'unloaded_cents' => 5000, 'unloaded_at' => now()]);
    pwLepo(['exchange' => Http::response(['message' => 'timeout'], 502)]);

    expect(pwRun($w)->status)->toBe('converting')->and($w->last_error)->toBe('EXCHANGE_CHECKING');
    $this->travel(16)->minutes();
    Cache::lock(LepoTopUpPipeline::CONVERSION_SLOT_KEY)->forceRelease();
    expect(pwRun($w)->status)->toBe('needs_attention')->and($w->last_error)->toBe('EXCHANGE_UNCLEAR');
    expect(pwCalls('/quantum-usd-to-crypto-exchange'))->toBe(1);
});

it('moves on when the exchange timed out but the USDC balance grew', function (): void {
    $w = pwWithdrawal(['status' => 'converting', 'unloaded_cents' => 5000, 'unloaded_at' => now()]);
    pwLepo([
        'exchange' => Http::response(['message' => 'timeout'], 502),
        'assets'   => [
            Http::response(['assets' => [['assetType' => 'crypto', 'currency' => 'USDC', 'availableBalance' => '3.000000']]]),
            Http::response(['assets' => [['assetType' => 'crypto', 'currency' => 'USDC', 'availableBalance' => '52.650500']]]),
        ],
    ]);

    $w = pwRun($w);
    expect($w->status)->toBe('awaiting_code')->and($w->usdc_amount)->toBe('49.650500');
    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/transfers/withdrawals/crypto') && $r['amount'] === '49.650500');
});

it('stops on an exchange amount mismatch', function (): void {
    $w = pwWithdrawal(['status' => 'converting', 'unloaded_cents' => 5000, 'unloaded_at' => now()]);
    pwLepo(['exchange' => Http::response(['success' => true, 'sourceAmount' => 62.10, 'cryptoAmountReceived' => 61.7, 'fee' => 0.25, 'tradeId' => 'tr-3'])]);

    expect(pwRun($w)->status)->toBe('needs_attention')->and($w->last_error)->toBe('EXCHANGE_AMOUNT_MISMATCH');
    expect(pwCalls('/transfers/withdrawals/crypto'))->toBe(0);
});

it('stops in needs_attention when the withdrawal request times out and never repeats it on its own', function (): void {
    $w = pwWithdrawal(['status' => 'converting', 'unloaded_cents' => 5000, 'unloaded_at' => now(), 'usdc_amount' => '49.650500', 'exchanged_at' => now()]);
    pwLepo(['request' => Http::response(['message' => 'timeout'], 504)]);

    expect(pwRun($w)->status)->toBe('needs_attention')->and($w->last_error)->toBe('WITHDRAWAL_UNCLEAR');
    pwRun($w);
    expect(pwCalls('/transfers/withdrawals/crypto'))->toBe(1);
});

it('stops when the withdrawal request is refused', function (): void {
    $w = pwWithdrawal(['status' => 'converting', 'unloaded_cents' => 5000, 'unloaded_at' => now(), 'usdc_amount' => '49.650500', 'exchanged_at' => now()]);
    pwLepo(['request' => Http::response(['message' => 'address not allowed', 'code' => 'ADDRESS_REJECTED'], 422)]);

    expect(pwRun($w)->status)->toBe('needs_attention')->and($w->last_error)->toBe('WITHDRAWAL_REFUSED');
    expect(CardWithdrawalEvent::query()->where('withdrawal_id', $w->id)->where('partner_http_status', 422)->value('note'))->toContain('ADDRESS_REJECTED');
});

it('goes straight to sending when no code is required', function (): void {
    $w = pwWithdrawal(['status' => 'converting', 'unloaded_cents' => 5000, 'unloaded_at' => now(), 'usdc_amount' => '49.650500', 'exchanged_at' => now()]);
    pwLepo(['request' => Http::response(['success' => true, 'verificationToken' => null, 'transactionId' => 'wd-2', 'status' => 'PROCESSING', 'otpRequired' => false], 201)]);

    expect(pwRun($w)->status)->toBe('sending')->and($w->confirmed_at)->not->toBeNull()->and($w->code_sent_at)->toBeNull();
});

it('cancels a withdrawal that has not reached the unload when the switch is off, and frees the user for a top-up', function (): void {
    config(['cardissuance.providers.lepo_withdrawals_enabled' => false, 'cardissuance.providers.enabled' => 'lepo', 'cardissuance.providers.lepo_pilot_only' => false,
        'cardissuance.providers.lepo_deposit_networks'        => 'arbitrum', 'cardissuance.providers.lepo_topup_min' => '10.00']);
    $w = pwWithdrawal();
    pwLepo();
    $this->push->shouldReceive('sendToUser')->once()->withArgs(fn ($u, string $type, $title, string $body, array $data): bool => $type === 'card.withdrawal.updated'
        && str_contains($body, 'still on your card') && $data['withdrawal_id'] === $w->id)->andReturn([]);

    $w = pwRun($w);

    expect($w->status)->toBe('cancelled')->and($w->last_error)->toBe('WITHDRAWALS_DISABLED')
        ->and(App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawals::present($w)['failure_reason'])->toBe('withdrawals_disabled');
    expect(pwCalls('/cards/unload-card'))->toBe(0);
    Event::assertDispatched(CardWithdrawalUpdated::class, fn (CardWithdrawalUpdated $e): bool => $e->withdrawalId === $w->id && $e->status === 'cancelled');

    // The user's top-ups are no longer blocked.
    $card = $w->card()->firstOrFail();
    CardFundingAccount::query()->whereKey($card->funding_account_id)->firstOrFail()
        ->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => '0x' . str_repeat('a', 40), 'is_active' => true]);
    Sanctum::actingAs($w->user()->firstOrFail(), ['read', 'write', 'delete']);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$card->id}/topups", ['amount' => '25.00', 'network' => 'arbitrum'])
        ->assertCreated()->assertJsonPath('data.status', 'awaiting_deposit');
});

it('keeps finishing rows past the unload while the switch is off', function (array $row, string $expected): void {
    config(['cardissuance.providers.lepo_withdrawals_enabled' => false]);
    $w = pwWithdrawal($row);
    pwLepo(['crypto' => Http::response(['transfers' => [['id' => 'il-1', 'status' => 'COMPLETED', 'amount' => ['amount' => 48.65], 'destination' => ['address' => PW_ADDRESS]]]])]);
    if ($expected === 'needs_attention') {
        $this->travel(73)->hours();
    }

    expect(pwRun($w)->status)->toBe($expected);
})->with([
    'unload call in flight' => [['step_started_at' => now(), 'unload_requested_at' => now(), 'card_balance_before_cents' => 6210], 'unloading'],
    'converting'            => [['status' => 'converting', 'unloaded_cents' => 5000, 'unloaded_at' => now()], 'awaiting_code'],
    'awaiting_code'         => [['status' => 'awaiting_code', 'code_sent_at' => now(), 'provider_verification_token' => 'tok-1'], 'needs_attention'],
    'sending'               => [['status' => 'sending', 'withdrawal_amount' => '49.650500', 'provider_transfer_id' => 'il-1', 'confirm_requested_at' => now()], 'completed'],
]);

it('cancels before the unload when support froze the card meanwhile', function (): void {
    $w = pwWithdrawal(cardStatus: 'frozen');
    CardStaffFreeze::place('777', null, CardStaffFreeze::SOURCE_STAFF);
    pwLepo();

    $w = pwRun($w);
    expect($w->status)->toBe('cancelled')->and($w->last_error)->toBe('CARD_FROZEN_BY_SUPPORT');
    expect(pwCalls('/cards/unload-card'))->toBe(0);
});

it('cancels before the unload when the card balance fell below the amount', function (): void {
    $w = pwWithdrawal();
    pwLepo(['card' => Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '20.00']])]);

    expect(pwRun($w)->status)->toBe('cancelled')->and($w->last_error)->toBe('BALANCE_CHANGED');
    expect(pwCalls('/cards/unload-card'))->toBe(0);
});

it('moves a code nobody entered for 72 hours to an operator, and loads nothing back', function (): void {
    $w = pwWithdrawal(['status' => 'awaiting_code', 'code_sent_at' => now(), 'provider_verification_token' => 'tok-1']);
    pwLepo();
    $this->push->shouldReceive('sendToUser')->once()->withArgs(fn ($u, $t, $title, string $body): bool => str_contains($body, 'taking longer'))->andReturn([]);

    $this->travel(71)->hours();
    expect(pwRun($w)->status)->toBe('awaiting_code');
    $this->travel(2)->hours();
    expect(pwRun($w)->status)->toBe('needs_attention')->and($w->last_error)->toBe('CODE_NOT_ENTERED');
    expect(collect(Http::recorded())->filter(fn (array $p): bool => $p[0]->method() === 'POST')->count())->toBe(0);
});

it('completes from the inbound transfer our wallet mirror saw', function (): void {
    $w = pwWithdrawal(['status' => 'sending', 'withdrawal_amount' => '49.650500', 'withdrawal_fee' => '1.000000', 'confirm_requested_at' => now(), 'confirmed_at' => now()]);
    pwLepo();
    $hash = '0x' . str_repeat('ab', 32);
    BlockchainTransaction::create(['address_uuid' => $w->destination_address_uuid, 'tx_hash' => $hash, 'type' => 'receive', 'amount' => '48.650500', 'from_address' => '0xfeed',
        'to_address'                              => PW_ADDRESS, 'chain' => 'arbitrum', 'status' => 'confirmed', 'metadata' => ['asset' => 'USDC']]);
    $this->push->shouldReceive('sendToUser')->once()->withArgs(fn ($u, $t, $title, string $body): bool => $body === 'The money from your card is in your wallet.')->andReturn([]);

    $w = pwRun($w);

    expect($w->status)->toBe('completed')->and($w->tx_hash)->toBe($hash)->and($w->received_amount)->toBe('48.650500')->and($w->completed_at)->not->toBeNull();
    Event::assertDispatched(CardWithdrawalUpdated::class, fn (CardWithdrawalUpdated $e): bool => $e->status === 'completed' && $e->receivedAmount === '48.65' && $e->codeExpiresAt === null);
});

it('ignores an inbound transfer of another amount or another asset', function (): void {
    $w = pwWithdrawal(['status' => 'sending', 'withdrawal_amount' => '49.650500', 'withdrawal_fee' => '1.000000', 'confirm_requested_at' => now()]);
    pwLepo();
    BlockchainTransaction::create(['address_uuid' => $w->destination_address_uuid, 'tx_hash' => '0x' . str_repeat('01', 32), 'type' => 'receive', 'amount' => '5.000000', 'from_address' => '0xfeed',
        'to_address'                              => PW_ADDRESS, 'chain' => 'arbitrum', 'status' => 'confirmed', 'metadata' => ['asset' => 'USDC']]);
    BlockchainTransaction::create(['address_uuid' => $w->destination_address_uuid, 'tx_hash' => '0x' . str_repeat('02', 32), 'type' => 'receive', 'amount' => '48.650500', 'from_address' => '0xfeed',
        'to_address'                              => PW_ADDRESS, 'chain' => 'arbitrum', 'status' => 'confirmed', 'metadata' => ['asset' => 'USDT']]);

    expect(pwRun($w)->status)->toBe('sending');
});

it('completes from the provider reporting the transfer completed', function (): void {
    $w = pwWithdrawal(['status' => 'sending', 'withdrawal_amount' => '49.650500', 'provider_transfer_id' => 'il-1', 'confirm_requested_at' => now()]);
    $hash = '0x' . str_repeat('cd', 32);
    pwLepo(['crypto' => Http::response(['transfers' => [['id' => 'il-1', 'status' => 'COMPLETED', 'amount' => ['amount' => 48.65, 'currency' => 'USDC'],
        'destination'                                         => ['type' => 'address', 'address' => PW_ADDRESS, 'chain' => 'ARB'], 'transactionHash' => $hash]]])]);

    $w = pwRun($w);
    expect($w->status)->toBe('completed')->and($w->received_amount)->toBe('48.650000')->and($w->tx_hash)->toBe($hash);
});

it('stops when the provider reports the transfer failed', function (): void {
    $w = pwWithdrawal(['status' => 'sending', 'withdrawal_amount' => '49.650500', 'provider_transfer_id' => 'il-1', 'confirm_requested_at' => now()]);
    pwLepo(['crypto' => Http::response(['transfers' => [['id' => 'il-1', 'status' => 'FAILED', 'destination' => ['address' => PW_ADDRESS]]]])]);

    expect(pwRun($w)->status)->toBe('needs_attention')->and($w->last_error)->toBe('SEND_FAILED');
});

it('stops an unclear confirm after an hour', function (): void {
    $unclear = pwWithdrawal(['status' => 'sending', 'last_error' => 'CONFIRM_CHECKING', 'withdrawal_amount' => '49.650500', 'confirm_requested_at' => now()]);
    pwLepo();

    $this->travel(59)->minutes();
    expect(pwRun($unclear)->status)->toBe('sending');
    $this->travel(2)->minutes();
    expect(pwRun($unclear)->status)->toBe('needs_attention')->and($unclear->last_error)->toBe('CONFIRM_UNCLEAR');
});

it('stops a confirmed transfer that has not arrived after a day', function (): void {
    pwLepo();
    $slow = pwWithdrawal(['status' => 'sending', 'withdrawal_amount' => '49.650500', 'confirm_requested_at' => now(), 'confirmed_at' => now()]);
    $this->travel(23)->hours();
    expect(pwRun($slow)->status)->toBe('sending');
    $this->travel(2)->hours();
    expect(pwRun($slow)->status)->toBe('needs_attention')->and($slow->last_error)->toBe('SEND_SLOW');
});

it('closes the card after a close_card withdrawal when the card is empty', function (): void {
    $w = pwWithdrawal(['status' => 'sending', 'withdraw_all' => true, 'close_card' => true, 'withdrawal_amount' => '49.650500', 'provider_transfer_id' => 'il-1', 'confirm_requested_at' => now()]);
    pwLepo([
        'crypto' => Http::response(['transfers' => [['id' => 'il-1', 'status' => 'COMPLETED', 'amount' => ['amount' => 48.65], 'destination' => ['address' => PW_ADDRESS]]]]),
        'card'   => Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '0', 'pending' => '0', 'frozen' => '0']]),
    ]);

    $w = pwRun($w);
    expect($w->status)->toBe('completed')->and($w->card_closed)->toBeTrue()->and($w->card?->fresh()?->status)->toBe('cancelled');
    expect(pwCalls('/cards/777', 'DELETE'))->toBe(1);
});

it('leaves the card open while pending payments hold money', function (): void {
    $w = pwWithdrawal(['status' => 'sending', 'withdraw_all' => true, 'close_card' => true, 'withdrawal_amount' => '49.650500', 'provider_transfer_id' => 'il-1', 'confirm_requested_at' => now()]);
    pwLepo([
        'crypto' => Http::response(['transfers' => [['id' => 'il-1', 'status' => 'COMPLETED', 'amount' => ['amount' => 48.65], 'destination' => ['address' => PW_ADDRESS]]]]),
        'card'   => Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '0', 'pending' => '3.20', 'frozen' => '0']]),
    ]);

    $w = pwRun($w);
    expect($w->status)->toBe('completed')->and($w->card_closed)->toBeFalse()->and($w->card_close_attempted_at)->not->toBeNull();
    expect(pwCalls('/cards/777', 'DELETE'))->toBe(0);
});

it('refuses to cancel a card while its withdrawal is active or unclear', function (string $status): void {
    $w = pwWithdrawal(['status' => $status]);
    Sanctum::actingAs($w->user()->firstOrFail(), ['read', 'write', 'delete']);
    config(['cardissuance.providers.enabled' => 'lepo', 'cardissuance.providers.lepo_pilot_only' => false]);
    pwLepo(['card' => Http::response(['id' => 777, 'status' => 'ACTIVE', 'balance' => ['available' => '0', 'pending' => '0', 'frozen' => '0']])]);

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$w->card_id}/cancel")
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_039')->assertJsonPath('error.kind', 'withdrawal');
    expect(pwCalls('/cards/777', 'DELETE'))->toBe(0);
})->with(['awaiting_code', 'needs_attention']);

it('explains how to empty the card when a cancel finds money on it', function (): void {
    $w = pwWithdrawal(['status' => 'completed']);
    Sanctum::actingAs($w->user()->firstOrFail(), ['read', 'write', 'delete']);
    config(['cardissuance.providers.enabled' => 'lepo', 'cardissuance.providers.lepo_pilot_only' => false]);
    pwLepo();

    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson("/api/v1/cards/issued/{$w->card_id}/cancel")
        ->assertStatus(409)->assertJsonPath('error.code', 'ERR_CARDS_025')->assertJsonPath('error.available_cents', 6210)
        ->assertJsonPath('error.withdrawals_enabled', true)
        ->assertJsonPath('error.message', 'This card still holds money. Send the money to your wallet first, then close the card.');
});

it('lets top-ups wait while a withdrawal is active', function (): void {
    $w = pwWithdrawal(['status' => 'awaiting_code']);
    $topup = new CardTopUp();
    $topup->forceFill(['user_id' => $w->user_id, 'card_id' => $w->card_id, 'provider' => 'lepo', 'source' => 'external_deposit', 'asset' => 'USDC', 'network' => 'arbitrum',
        'amount'                 => '25', 'received_amount' => '25', 'deposit_address' => '0xarb', 'status' => 'deposit_received', 'provider_deposit_id' => 'dep-1', 'expires_at' => now()])->save();
    Http::fake();

    expect(app(LepoTopUpPipeline::class)->advance($topup)->status)->toBe('deposit_received');
    Http::assertNothingSent();
});

it('only wakes the job on card.unload and wallet.withdrawal webhooks', function (string $type): void {
    $w = pwWithdrawal(['status' => 'sending', 'confirm_requested_at' => now()]);
    Bus::fake([AdvanceLepoWithdrawals::class, AdvanceLepoTopUps::class]);

    app(LepoWebhookRouter::class)->route($type, ['userId' => '12345', 'transactionId' => 'x', 'status' => 'completed', 'amount' => '48.65'], 'evt-1');

    Bus::assertDispatched(AdvanceLepoWithdrawals::class, fn (AdvanceLepoWithdrawals $job): bool => $job->lepoUserId === '12345');
    expect($w->refresh()->status)->toBe('sending');
})->with(['card.unload', 'wallet.withdrawal']);

it('queues one job per Lepo user with work left from the scheduled run', function (): void {
    $w = pwWithdrawal(['status' => 'sending', 'confirm_requested_at' => now()]);
    pwWithdrawal(['status' => 'completed'], token: '778');
    Bus::fake([AdvanceLepoWithdrawals::class]);

    (new AdvanceLepoWithdrawals())->handle();

    Bus::assertDispatchedTimes(AdvanceLepoWithdrawals::class, 1);
    expect(app(LepoWithdrawalPipeline::class))->toBeInstanceOf(LepoWithdrawalPipeline::class)->and($w->status)->toBe('sending');
});
