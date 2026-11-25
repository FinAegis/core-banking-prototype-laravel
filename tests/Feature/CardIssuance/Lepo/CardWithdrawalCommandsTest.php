<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Events\Broadcast\CardWithdrawalUpdated;
use App\Domain\CardIssuance\Jobs\AdvanceLepoWithdrawals;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Models\CardWithdrawalEvent;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUpPipeline;
use App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawalPipeline;
use App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawals;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'cache.default'                                      => 'array',
        'cardissuance.issuers.lepo.base_url'                 => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'                  => 'k',
        'cardissuance.providers.lepo_withdrawals_enabled'    => true,
        'cardissuance.providers.lepo_withdrawal_networks'    => 'arbitrum,polygon',
        'cardissuance.providers.lepo_withdrawal_chain_codes' => 'arbitrum:ARB,polygon:MATIC',
    ]);
    Cache::lock(LepoTopUpPipeline::CONVERSION_SLOT_KEY)->forceRelease();
    Http::preventStrayRequests();
    Event::fake([CardWithdrawalUpdated::class]);
});

/**
 * @param  array<model-property<CardWithdrawal>, mixed>  $over
 */
function cwWithdrawal(array $over = [], string $token = '777'): CardWithdrawal
{
    $user = User::factory()->create(['email' => 'ada-' . Str::random(6) . '@example.com']);
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345']);
    $holder->save();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'provider' => 'lepo', 'status' => 'active']);
    $card = Card::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'funding_account_id' => $account->id, 'issuer' => 'lepo', 'status' => 'active', 'issuer_card_token' => $token]);

    $w = new CardWithdrawal();
    $w->forceFill(array_merge([
        'user_id'             => $user->id, 'card_id' => $card->id, 'provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC',
        'destination_address' => '0x' . str_repeat('5', 40), 'destination_address_uuid' => (string) Str::uuid(), 'amount' => '50.00', 'status' => 'needs_attention',
        'unloaded_cents'      => 5000, 'unloaded_at' => now(), 'usdc_amount' => '49.650500', 'exchanged_at' => now(), 'withdrawal_key' => (string) Str::uuid(),
        'withdrawal_attempts' => 1, 'withdrawal_requested_at' => now(), 'last_error' => 'WITHDRAWAL_UNCLEAR', 'provider_verification_token' => 'tok-hidden',
    ], $over))->save();

    return $w;
}

it('prints the record and the audit trail without the token', function (): void {
    $w = cwWithdrawal();
    App\Domain\CardIssuance\Providers\Lepo\Withdrawals\WithdrawalJournal::record($w, 'job', 'call_started', ['note' => 'withdrawal request']);

    $this->artisan('cards:inspect-withdrawal', ['withdrawal' => $w->id, '--no-live' => true])
        ->expectsOutputToContain('WITHDRAWAL_UNCLEAR')
        ->expectsOutputToContain('withdrawal request')
        ->expectsOutputToContain('(set, not shown)')
        ->doesntExpectOutputToContain('tok-hidden')
        ->assertSuccessful();
});

it('reads the live view at Lepo without moving money', function (): void {
    $w = cwWithdrawal();
    Http::fake([
        'lepo.test/api/v2/cards/777*'                     => Http::response(['id' => 777, 'balance' => ['available' => '12.10', 'pending' => '0', 'frozen' => '0']]),
        'lepo.test/api/v2/users/12345/assets'             => Http::response(['assets' => [['currency' => 'USD', 'assetType' => 'fiat', 'availableBalance' => '0'], ['currency' => 'USDC', 'availableBalance' => '49.650500']]]),
        'lepo.test/api/v2/transactions*'                  => Http::response(['data' => [['id' => 9, 'type' => 'crypto_withdrawal', 'status' => 'pending', 'amount' => 49.65, 'description' => 'Ada Lovelace']]]),
        'lepo.test/api/v2/transfers/crypto-transactions*' => Http::response(['transfers' => []]),
    ]);

    $this->artisan('cards:inspect-withdrawal', ['withdrawal' => $w->id])
        ->expectsOutputToContain('49.650500')
        ->expectsOutputToContain('crypto_withdrawal')
        ->doesntExpectOutputToContain('Ada Lovelace')
        ->assertSuccessful();
    Http::assertNotSent(fn (Request $r): bool => $r->method() !== 'GET');
});

it('needs a note and an operator', function (): void {
    $w = cwWithdrawal();

    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'failed', '--money-location' => 'usdc', '--operator-email' => 'ops@zelta.app'])->assertFailed();
    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'failed', '--money-location' => 'usdc', '--note' => 'checked'])->assertFailed();
    expect($w->refresh()->status)->toBe('needs_attention');
});

it('closes a withdrawal as completed with the amount and hash', function (): void {
    $w = cwWithdrawal();
    $hash = '0x' . str_repeat('ef', 32);

    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'completed', '--note' => 'seen on chain', '--operator-email' => 'ops@zelta.app'])->assertFailed();
    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'completed', '--received-amount' => '48.65', '--tx-hash' => $hash, '--note' => 'seen on chain', '--operator-email' => 'ops@zelta.app'])
        ->assertSuccessful();

    $w->refresh();
    expect($w->status)->toBe('completed')->and($w->received_amount)->toBe('48.650000')->and($w->tx_hash)->toBe($hash);
    expect(CardWithdrawalEvent::query()->where('withdrawal_id', $w->id)->where('action', 'resolved')->value('actor'))->toBe('operator:ops@zelta.app');
});

it('closes a withdrawal as failed with where the money is', function (): void {
    $w = cwWithdrawal();

    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'failed', '--note' => 'refused', '--operator-email' => 'ops@zelta.app'])->assertFailed();
    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'failed', '--money-location' => 'usdc', '--note' => 'refused', '--operator-email' => 'ops@zelta.app'])->assertSuccessful();

    expect($w->refresh()->status)->toBe('failed')->and(CardWithdrawalEvent::query()->where('withdrawal_id', $w->id)->where('action', 'resolved')->value('note'))->toContain('money: usdc');
});

it('takes the money lock', function (): void {
    $w = cwWithdrawal();
    $lock = Cache::lock(LepoWithdrawals::moneyLockKey((int) $w->user_id), 60);
    $lock->get();

    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'failed', '--money-location' => 'usdc', '--note' => 'x', '--operator-email' => 'ops@zelta.app'])->assertFailed();
    expect($w->refresh()->status)->toBe('needs_attention');
    $lock->forceRelease();
});

it('resumes an unclear withdrawal request once, with the same key', function (): void {
    $w = cwWithdrawal();
    $key = $w->withdrawal_key;
    Bus::fake([AdvanceLepoWithdrawals::class]);

    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'resume', '--note' => 'no request at Lepo', '--operator-email' => 'ops@zelta.app'])->assertSuccessful();
    expect($w->refresh()->status)->toBe('converting')->and($w->withdrawal_requested_at)->toBeNull();
    Bus::assertDispatched(AdvanceLepoWithdrawals::class);

    Http::fake([
        'lepo.test/api/v2/transfers/withdrawals/fee-and-quota*' => Http::response(['data' => ['fees' => [['amount' => '1.00', 'currency' => 'USDC']]]]),
        'lepo.test/api/v2/transfers/withdrawals/crypto'         => Http::response(['message' => 'timeout'], 504),
    ]);
    app(LepoWithdrawalPipeline::class)->advance($w);

    Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/transfers/withdrawals/crypto') && $r->header('Idempotency-Key')[0] === $key);
    expect($w->refresh()->status)->toBe('needs_attention')->and($w->withdrawal_attempts)->toBe(2);

    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'resume', '--note' => 'again', '--operator-email' => 'ops@zelta.app'])->assertFailed();
});

it('resumes after an unclear unload or exchange only with the amount that moved', function (): void {
    Bus::fake([AdvanceLepoWithdrawals::class]);
    $unload = cwWithdrawal(['unloaded_cents' => null, 'unloaded_at' => null, 'usdc_amount' => null, 'exchanged_at' => null, 'withdrawal_requested_at' => null, 'last_error' => 'UNLOAD_UNCLEAR']);

    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $unload->id, 'outcome' => 'resume', '--note' => 'unloaded', '--operator-email' => 'ops@zelta.app'])->assertFailed();
    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $unload->id, 'outcome' => 'resume', '--unloaded-cents' => '5000', '--note' => 'unloaded', '--operator-email' => 'ops@zelta.app'])->assertSuccessful();
    expect($unload->refresh()->status)->toBe('converting')->and($unload->unloaded_cents)->toBe(5000)->and($unload->exchanged_at)->toBeNull();

    $exchange = cwWithdrawal(['usdc_amount' => null, 'exchanged_at' => null, 'withdrawal_requested_at' => null, 'last_error' => 'EXCHANGE_UNCLEAR'], '778');
    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $exchange->id, 'outcome' => 'resume', '--usdc-amount' => '49.65', '--note' => 'exchanged', '--operator-email' => 'ops@zelta.app'])->assertSuccessful();
    expect($exchange->refresh()->usdc_amount)->toBe('49.650000')->and($exchange->exchanged_at)->not->toBeNull();
});

it('refuses to resume a locked code until the partner answers', function (): void {
    $w = cwWithdrawal(['last_error' => 'CODE_LOCKED', 'code_sent_at' => now()]);

    $this->artisan('cards:resolve-withdrawal', ['withdrawal' => $w->id, 'outcome' => 'resume', '--note' => 'x', '--operator-email' => 'ops@zelta.app'])->assertFailed();
});

it('lists withdrawals next to top-ups for a user', function (): void {
    $w = cwWithdrawal();

    $this->artisan('cards:inspect-user', ['email' => $w->user()->firstOrFail()->email])
        ->expectsOutputToContain($w->id)
        ->assertSuccessful();
});
