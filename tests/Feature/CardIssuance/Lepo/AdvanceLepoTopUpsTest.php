<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Events\Broadcast\CardTopUpUpdated;
use App\Domain\CardIssuance\Jobs\AdvanceLepoTopUps;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\Lepo\LepoTopUpPipeline;
use App\Domain\CardIssuance\Webhooks\LepoWebhookRouter;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

const AT_ADDRESS = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

beforeEach(function (): void {
    // The shared 15-second conversion slot outlives a test when CI keeps the cache in Redis.
    Cache::lock(LepoTopUpPipeline::CONVERSION_SLOT_KEY)->forceRelease();
    config([
        'cardissuance.issuers.lepo.base_url'             => 'https://lepo.test/api/v2', 'cardissuance.issuers.lepo.api_key' => 'k',
        'cardissuance.issuers.lepo.card_type_id_virtual' => '145', 'cardissuance.providers.lepo_topups_enabled' => true,
    ]);
    Event::fake([CardTopUpUpdated::class]);
    // Unique-job and money locks live in the cache; CI shares one Redis
    // between tests, so these tests keep their own cache.
    config(['cache.default' => 'array']);
});

/**
 * @return array{0: User, 1: Cardholder, 2: CardFundingAccount}
 */
function atHolder(string $email = 'ada@example.com'): array
{
    $user = User::factory()->create(['email' => $email]);
    $holder = new Cardholder();
    $holder->forceFill(['user_id' => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+37060000000', 'kyc_status' => 'verified', 'issuer_cardholder_id' => '12345']);
    $holder->save();
    $account = CardFundingAccount::create(['user_id' => $user->id, 'cardholder_id' => $holder->id, 'provider' => 'lepo', 'status' => 'active']);
    $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'arbitrum', 'asset' => 'USDC', 'address' => AT_ADDRESS, 'is_active' => true]);

    return [$user, $holder, $account];
}

function atCard(Cardholder $holder, CardFundingAccount $account, string $status = 'active', ?string $token = '777'): Card
{
    return Card::create(['user_id' => $holder->user_id, 'cardholder_id' => $holder->id, 'funding_account_id' => $account->id, 'issuer' => 'lepo', 'status' => $status,
        'issuer_card_token'        => $token, 'last4' => $token === null ? null : '1234', 'network' => $token === null ? null : 'visa']);
}

/**
 * @param  array<model-property<CardTopUp>, mixed>  $overrides
 */
function atTopUp(Card $card, array $overrides = []): CardTopUp
{
    $topup = new CardTopUp();
    $topup->forceFill(array_merge([
        'user_id' => $card->user_id, 'card_id' => $card->id, 'funding_account_id' => $card->funding_account_id, 'provider' => 'lepo', 'source' => 'app', 'asset' => 'USDC',
        'network' => 'arbitrum', 'amount' => '25.000000', 'deposit_address' => AT_ADDRESS, 'status' => 'awaiting_deposit', 'expires_at' => now()->addHour(),
    ], $overrides))->save();

    return $topup;
}

/**
 * @param  list<array<string, mixed>>  $transfers
 */
function atLepo(array $transfers): void
{
    $opened = false;
    Http::fake(function (Request $r) use ($transfers, &$opened) {
        $path = (string) parse_url($r->url(), PHP_URL_PATH);

        return match (true) {
            str_ends_with($path, '/transfers/crypto-transactions')          => Http::response(['transfers' => $transfers]),
            str_ends_with($path, '/transfers/crypto-to-quantum-transfer')   => Http::response(['success' => true, 'transferId' => 'tr-1', 'details' => ['status' => 'COMPLETED']]),
            str_ends_with($path, '/users/12345/assets')                     => Http::response(['assets' => [['assetType' => 'fiat', 'balanceType' => 'QuantumAccount', 'balanceId' => 'b-usd', 'currency' => 'USD', 'availableBalance' => $opened ? '23.90' : '24.90']]]),
            $r->method() === 'POST' && str_ends_with($path, '/cards/topup') => Http::response(['success' => true, 'orderId' => 'ord-1', 'requestedValue' => $r['value'], 'grossValue' => $r['value'], 'fee' => '0', 'value' => $r['value'], 'isPartial' => false]),
            $r->method() === 'POST' && str_ends_with($path, '/cards')       => (function () use (&$opened) {
                $opened = true;

                return Http::response(['id' => 888, 'status' => 'ACTIVE', 'cardNumber' => '****5678', 'balance' => ['available' => '0', 'pending' => '0', 'frozen' => '0']], 201);
            })(),
            $r->method() === 'GET' && str_ends_with($path, '/cards') => Http::response(['cards' => []]),
            default                                                  => Http::response(['id' => 777, 'status' => 'ACTIVE', 'cardNumber' => '****1234', 'balance' => ['available' => '24.90', 'pending' => '0', 'frozen' => '0']]),
        };
    });
}

/**
 * @return array<string, mixed>
 */
function atDeposit(string $id = 'dep-1', float $amount = 25.0): array
{
    return ['id' => $id, 'status' => 'COMPLETED', 'destination' => ['chain' => 'ARBITRUM', 'address' => AT_ADDRESS], 'amount' => ['amount' => $amount, 'currency' => 'USDC']];
}

it('advances a top-up when its deposit arrives', function (): void {
    [, $holder, $account] = atHolder();
    $topup = atTopUp(atCard($holder, $account));
    atLepo([atDeposit()]);

    (new AdvanceLepoTopUps('12345'))->handle();

    expect($topup->fresh()?->status)->toBe('completed')->and($topup->fresh()?->credited_cents)->toBe(2490);
});

it('expires a top-up nobody funded', function (): void {
    [, $holder, $account] = atHolder();
    $topup = atTopUp(atCard($holder, $account), ['expires_at' => now()->subMinute()]);
    atLepo([]);

    (new AdvanceLepoTopUps('12345'))->handle();

    expect($topup->fresh()?->status)->toBe('expired');
    Event::assertDispatched(CardTopUpUpdated::class, fn (CardTopUpUpdated $e): bool => $e->status === 'expired');
});

it('turns an unmatched deposit into an external top-up', function (): void {
    [, $holder, $account] = atHolder();
    $card = atCard($holder, $account);
    atLepo([atDeposit('dep-ext', 40.0)]);

    (new AdvanceLepoTopUps('12345'))->handle();

    $external = CardTopUp::query()->where('provider_deposit_id', 'dep-ext')->firstOrFail();
    expect($external->source)->toBe('external_deposit')
        ->and($external->card_id)->toBe($card->id)
        ->and($external->received_amount)->toBe('40.000000')
        ->and($external->status)->toBe('completed');
});

it('opens a card for an external deposit when the user has none', function (): void {
    [, $holder] = atHolder();
    atLepo([atDeposit('dep-ext', 40.0)]);

    (new AdvanceLepoTopUps('12345'))->handle();

    $external = CardTopUp::query()->where('provider_deposit_id', 'dep-ext')->firstOrFail();
    expect($external->status)->toBe('completed')
        ->and($external->issuance_fee_cents)->toBe(100)
        ->and(Card::query()->where('cardholder_id', $holder->id)->value('issuer_card_token'))->toBe('888');
});

it('creates one external top-up per deposit however often it runs', function (): void {
    [, $holder, $account] = atHolder();
    atCard($holder, $account);
    atLepo([atDeposit('dep-ext', 40.0)]);

    (new AdvanceLepoTopUps('12345'))->handle();
    (new AdvanceLepoTopUps('12345'))->handle();

    expect(CardTopUp::query()->where('provider_deposit_id', 'dep-ext')->count())->toBe(1);
});

it('records a deposit for a user whose only card is blocked', function (): void {
    [, $holder, $account] = atHolder();
    atCard($holder, $account, 'blocked');
    atLepo([atDeposit('dep-blocked', 40.0)]);

    (new AdvanceLepoTopUps('12345'))->handle();

    $row = CardTopUp::query()->where('provider_deposit_id', 'dep-blocked')->firstOrFail();
    expect($row->card_id)->toBeNull()->and($row->status)->toBe('needs_attention')->and($row->last_error)->toBe('NO_LOADABLE_CARD')
        ->and(Card::query()->where('cardholder_id', $holder->id)->count())->toBe(1);
    expect(collect(Http::recorded())->filter(fn (array $p): bool => str_contains($p[0]->url(), 'crypto-to-quantum'))->count())->toBe(0);
});

it('ignores zero and tiny deposits', function (): void {
    [, $holder] = atHolder();
    atLepo([atDeposit('dep-zero', 0.0), atDeposit('dep-dust', 0.5)]);

    (new AdvanceLepoTopUps('12345'))->handle();

    expect(CardTopUp::query()->count())->toBe(0)->and(Card::query()->where('cardholder_id', $holder->id)->count())->toBe(0);
});

it('records a small deposit without converting it', function (): void {
    [, $holder, $account] = atHolder();
    atCard($holder, $account);
    atLepo([atDeposit('dep-small', 3.0)]);

    (new AdvanceLepoTopUps('12345'))->handle();

    $row = CardTopUp::query()->where('provider_deposit_id', 'dep-small')->firstOrFail();
    expect($row->status)->toBe('needs_attention')->and($row->last_error)->toBe('BELOW_MINIMUM');
});

it('queues one job per cardholder for the scheduled run', function (): void {
    Queue::fake();
    atHolder('a@example.com');
    [, $second] = atHolder('b@example.com');
    $second->forceFill(['issuer_cardholder_id' => '67890'])->save();

    (new AdvanceLepoTopUps())->handle();

    Queue::assertPushed(AdvanceLepoTopUps::class, 2);
    Queue::assertPushed(AdvanceLepoTopUps::class, fn (AdvanceLepoTopUps $job): bool => $job->lepoUserId === '67890' && $job->uniqueId() === '67890');
    expect((new AdvanceLepoTopUps('1'))->timeout)->toBeGreaterThanOrEqual(180);
});

it('lets an operator close a top-up after reconciling it', function (): void {
    [, $holder, $account] = atHolder('ops2@example.com');
    $topup = atTopUp(atCard($holder, $account), ['status' => 'needs_attention', 'last_error' => 'LOAD_UNCLEAR', 'provider_deposit_id' => 'dep-r']);

    $exit = Artisan::call('cards:resolve-topup', ['topup' => $topup->id, 'outcome' => 'completed', '--credited-cents' => 2390, '--note' => 'checked at Lepo']);

    expect($exit)->toBe(0)
        ->and($topup->fresh()?->status)->toBe('completed')
        ->and($topup->fresh()?->credited_cents)->toBe(2390)
        ->and($topup->fresh()?->last_error)->toBe('LOAD_UNCLEAR; resolved: checked at Lepo');
});

it('wakes up on Lepo deposit webhooks', function (string $type): void {
    Queue::fake();

    app(LepoWebhookRouter::class)->route($type, ['userId' => '12345', 'amount' => '25.00'], 'evt_w1');

    Queue::assertPushed(AdvanceLepoTopUps::class, fn (AdvanceLepoTopUps $job): bool => $job->lepoUserId === '12345');
})->with(['wallet.topped_up', 'card.topup']);

it('does nothing while the switch is off', function (): void {
    config(['cardissuance.providers.lepo_topups_enabled' => false]);
    [, $holder, $account] = atHolder();
    $topup = atTopUp(atCard($holder, $account));
    atLepo([atDeposit()]);

    (new AdvanceLepoTopUps('12345'))->handle();

    expect($topup->fresh()?->status)->toBe('awaiting_deposit');
    Http::assertNothingSent();
});

it('shows a user\'s top-ups to the operator', function (): void {
    [, $holder, $account] = atHolder('ops@example.com');
    $topup = atTopUp(atCard($holder, $account), ['status' => 'needs_attention', 'last_error' => 'LOAD_UNCLEAR']);

    $exit = Artisan::call('cards:inspect-user', ['email' => 'ops@example.com']);
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain($topup->id)->toContain('needs_attention')->toContain('LOAD_UNCLEAR')->toContain('verified');
});
