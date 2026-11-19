<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\CardIssuance\Adapters\DemoCardIssuerAdapter;
use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Enums\WalletType;
use App\Domain\CardIssuance\Events\AuthorizationApproved;
use App\Domain\CardIssuance\Events\AuthorizationDeclined;
use App\Domain\CardIssuance\Exceptions\UnsupportedCardOperationException;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Domain\CardIssuance\Services\JitFundingService;
use App\Domain\CardIssuance\Services\SpendLimitEnforcementService;
use App\Domain\CardIssuance\ValueObjects\AuthorizationRequest;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use App\Infrastructure\FinCard\FinCardClient;
use App\Infrastructure\FinCard\FinCardWebhookVerifier;
use App\Infrastructure\Monitoring\MetricsService;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

/*
|--------------------------------------------------------------------------
| GHSA-385w-q6j4-5qw6 — card issuance cluster
|--------------------------------------------------------------------------
|
| C03/D15  FinCard open/top-up need the caller's own active funding account
| C47      owners keep access to their cancelled / unlisted issuer cards
| C50      the demo issuer refuses to issue in production (clean 400 / GraphQL error)
| D09      JIT locks the account on its own (tenant) connection
| D10      unsigned FinCard webhooks only in local/testing
*/

const GHSA385W_FINCARD_BASE = 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual';

function ghsa385wCardsIdem(): string
{
    return (string) Str::uuid();
}

/**
 * @param  array<string, mixed>  $overrides
 */
function ghsa385wCardsFakeFinCard(array $overrides = []): void
{
    // Fresh factory: Http::fake() stubs stack and the first match wins.
    Http::swap(new HttpFactory(app('events')));
    Http::fake(array_merge([
        'sandbox.finhub.cloud/api/v2.1/admin/*'                          => Http::response(['success' => true, 'data' => ['accessToken' => 'jwt', 'expiresIn' => 3600]]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/openCard' => Http::response(['success' => true, 'data' => ['cardId' => 'card-new', 'last4' => '1234']]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/deposit'     => Http::response(['success' => true, 'data' => []]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/account/create'   => Http::response(['success' => true, 'data' => ['accountId' => 'acc-created']]),
        'sandbox.finhub.cloud/api/v2.1/fincard/virtual/*'                => Http::response(['success' => true, 'data' => ['address' => '0xabc', 'chain' => 'TRON']]),
    ], $overrides));
}

function ghsa385wCardsCardholder(User $user): Cardholder
{
    $c = new Cardholder();
    $c->user_id = (string) $user->id;
    $c->first_name = 'Jane';
    $c->last_name = 'Smith';
    $c->kyc_status = 'verified';
    $c->issuer_cardholder_id = 'h-' . $user->id;
    $c->save();

    return $c;
}

function ghsa385wCardsAccount(User $user, string $accountId = 'acc-1', string $status = 'active', int $balanceCents = 50000): FinCardAccount
{
    $a = new FinCardAccount();
    $a->user_id = (string) $user->id;
    $a->fincard_account_id = $accountId;
    $a->currency = 'USD';
    $a->balance_cents = $balanceCents;
    $a->status = $status;
    $a->save();

    return $a;
}

function ghsa385wCardsCard(User $user, Cardholder $cardholder, string $accountId = 'acc-1', int $balanceCents = 20000): Card
{
    $card = new Card();
    $card->user_id = (string) $user->id;
    $card->cardholder_id = $cardholder->id;
    $card->issuer_card_token = 'card-1';
    $card->issuer = 'fincard';
    $card->last4 = '4242';
    $card->network = 'visa';
    $card->status = 'active';
    $card->currency = 'USD';
    $card->balance_cents = $balanceCents;
    $card->fincard_account_id = $accountId;
    $card->save();

    return $card;
}

function ghsa385wCardsSentTo(string $path): bool
{
    return Http::recorded(fn (HttpRequest $r): bool => str_ends_with(parse_url($r->url(), PHP_URL_PATH) ?: '', $path))->isNotEmpty();
}

describe('C03/D15 FinCard open and top-up require the caller\'s funding account', function (): void {
    beforeEach(function (): void {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->app->instance(FinCardClient::class, new FinCardClient(
            baseUrl: GHSA385W_FINCARD_BASE,
            tenantId: 't',
            orgId: 'o',
            userId: 'u',
            username: 'x',
            password: 'y',
        ));
        ghsa385wCardsFakeFinCard();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['read', 'write', 'delete']);
    });

    it('refuses to open a card when the verified cardholder has no funding account', function (): void {
        ghsa385wCardsCardholder($this->user);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/open', ['card_type_id' => 111001, 'amount_cents' => 100000000])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ERR_CARDS_020');

        expect(ghsa385wCardsSentTo('/card/v2/openCard'))->toBeFalse()
            ->and(Card::query()->where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('refuses to open a card against a funding account with a blank FinCard id', function (): void {
        ghsa385wCardsCardholder($this->user);
        ghsa385wCardsAccount($this->user, accountId: '');

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/open', ['card_type_id' => 111001, 'amount_cents' => 20000])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ERR_CARDS_020');

        expect(ghsa385wCardsSentTo('/card/v2/openCard'))->toBeFalse()
            ->and(Card::query()->where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('refuses to open a card against a funding account that is not active', function (): void {
        ghsa385wCardsCardholder($this->user);
        ghsa385wCardsAccount($this->user, status: 'frozen');

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/open', ['card_type_id' => 111001, 'amount_cents' => 20000])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ERR_CARDS_020');

        expect(ghsa385wCardsSentTo('/card/v2/openCard'))->toBeFalse()
            ->and(Card::query()->where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('opens a card against the caller\'s own active funding account', function (): void {
        ghsa385wCardsCardholder($this->user);
        ghsa385wCardsAccount($this->user, accountId: 'acc-1');

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/open', ['card_type_id' => 111001, 'amount_cents' => 20000])
            ->assertCreated()
            ->assertJsonPath('data.card_id', 'card-new')
            ->assertJsonPath('data.balance_cents', 20000);

        Http::assertSent(fn (HttpRequest $r): bool => str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/card/v2/openCard')
            && $r['accountId'] === 'acc-1'
            && $r['amount'] === '200.00');

        $card = Card::query()->where('issuer_card_token', 'card-new')->first();
        expect($card)->not->toBeNull()
            ->and($card?->fincard_account_id)->toBe('acc-1');
    });

    it('persists nothing when the issuer rejects an open above the account balance', function (): void {
        ghsa385wCardsCardholder($this->user);
        ghsa385wCardsAccount($this->user, balanceCents: 5000);
        ghsa385wCardsFakeFinCard([
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/v2/openCard' => Http::response(['success' => false, 'code' => 4001, 'msg' => 'insufficient account balance']),
        ]);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/open', ['card_type_id' => 111001, 'amount_cents' => 100000000])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'ERR_CARDS_016');

        expect(Card::query()->where('user_id', $this->user->id)->count())->toBe(0);
    });

    it('refuses to top up a card bound to a blank funding account', function (): void {
        $ch = ghsa385wCardsCardholder($this->user);
        ghsa385wCardsAccount($this->user, accountId: 'acc-1');
        $card = ghsa385wCardsCard($this->user, $ch, accountId: '', balanceCents: 20000);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/card-1/topup', ['amount_cents' => 100000000])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ERR_CARDS_020');

        expect(ghsa385wCardsSentTo('/card/deposit'))->toBeFalse()
            ->and((int) $card->fresh()?->balance_cents)->toBe(20000);
    });

    it('refuses to top up a card bound to an account other than the caller\'s current one', function (): void {
        $ch = ghsa385wCardsCardholder($this->user);
        ghsa385wCardsAccount($this->user, accountId: 'acc-1');
        $card = ghsa385wCardsCard($this->user, $ch, accountId: 'acc-old', balanceCents: 20000);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/card-1/topup', ['amount_cents' => 5000])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ERR_CARDS_020');

        expect(ghsa385wCardsSentTo('/card/deposit'))->toBeFalse()
            ->and((int) $card->fresh()?->balance_cents)->toBe(20000);
    });

    it('refuses to top up when the caller has no funding account any more', function (): void {
        $ch = ghsa385wCardsCardholder($this->user);
        $card = ghsa385wCardsCard($this->user, $ch, accountId: 'acc-1', balanceCents: 20000);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/card-1/topup', ['amount_cents' => 5000])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'ERR_CARDS_020');

        expect(ghsa385wCardsSentTo('/card/deposit'))->toBeFalse()
            ->and((int) $card->fresh()?->balance_cents)->toBe(20000);
    });

    it('tops up a card bound to the caller\'s current funding account', function (): void {
        $ch = ghsa385wCardsCardholder($this->user);
        ghsa385wCardsAccount($this->user, accountId: 'acc-1');
        $card = ghsa385wCardsCard($this->user, $ch, accountId: 'acc-1', balanceCents: 20000);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/card-1/topup', ['amount_cents' => 5000])
            ->assertOk()
            ->assertJsonPath('data.balance_cents', 25000);

        expect(ghsa385wCardsSentTo('/card/deposit'))->toBeTrue()
            ->and((int) $card->fresh()?->balance_cents)->toBe(25000);
    });

    it('keeps the card mirror unchanged when the issuer rejects a top-up', function (): void {
        $ch = ghsa385wCardsCardholder($this->user);
        ghsa385wCardsAccount($this->user, accountId: 'acc-1', balanceCents: 1000);
        $card = ghsa385wCardsCard($this->user, $ch, accountId: 'acc-1', balanceCents: 20000);
        ghsa385wCardsFakeFinCard([
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/card/deposit' => Http::response(['success' => false, 'code' => 4001, 'msg' => 'balance too low']),
        ]);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/fincard/card-1/topup', ['amount_cents' => 100000000])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'ERR_CARDS_016');

        expect((int) $card->fresh()?->balance_cents)->toBe(20000);
    });

    it('does not persist a funding account when FinCard returns no account id', function (): void {
        ghsa385wCardsFakeFinCard([
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/account/create' => Http::response(['success' => true, 'data' => []]),
        ]);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/account/deposit-address', ['coin_key' => 'USDT_TRC20'])
            ->assertStatus(502)
            ->assertJsonPath('error.code', 'ERR_CARDS_013');

        expect(FinCardAccount::query()->where('user_id', $this->user->id)->exists())->toBeFalse()
            ->and(ghsa385wCardsSentTo('/wallet/v2/create'))->toBeFalse();
    });

    it('persists the funding account when FinCard returns its id', function (): void {
        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/account/deposit-address', ['coin_key' => 'USDT_TRC20'])
            ->assertCreated();

        expect(FinCardAccount::query()->where('user_id', $this->user->id)->value('fincard_account_id'))->toBe('acc-created');
    });

    it('re-provisions a legacy funding account row that has a blank FinCard id', function (): void {
        $legacy = ghsa385wCardsAccount($this->user, accountId: '', balanceCents: 0);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/account/deposit-address', ['coin_key' => 'USDT_TRC20'])
            ->assertCreated();

        expect($legacy->fresh()?->fincard_account_id)->toBe('acc-created')
            ->and(FinCardAccount::query()->where('user_id', $this->user->id)->count())->toBe(1);
    });
});

describe('C47 owners keep access to their cancelled or unlisted cards', function (): void {
    it('lets the owner read a cancelled demo card and its transactions; others get 404', function (): void {
        config(['cardissuance.default_issuer' => 'demo']);
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $card = (new DemoCardIssuerAdapter())->createCard((string) $owner->id, 'Owner Name');
        (new DemoCardIssuerAdapter())->cancelCard($card->cardToken, 'lost');

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->getJson("/api/v1/cards/{$card->cardToken}")
            ->assertOk()
            ->assertJsonPath('data.card_token', $card->cardToken)
            ->assertJsonPath('data.status', 'cancelled');
        $this->getJson("/api/v1/cards/{$card->cardToken}/transactions")->assertOk();

        Sanctum::actingAs($other, ['read', 'write', 'delete']);
        $this->getJson("/api/v1/cards/{$card->cardToken}")
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'ERR_CARD_NOT_FOUND');
        $this->getJson("/api/v1/cards/{$card->cardToken}/transactions")->assertStatus(404);
        $this->postJson("/api/v1/cards/{$card->cardToken}/freeze")->assertStatus(404);

        expect((new DemoCardIssuerAdapter())->getCard($card->cardToken)?->status)->toBe(CardStatus::CANCELLED);
    });

    it('lets the owner read a Marqeta card missing from the user listing; others get 404', function (): void {
        config([
            'cardissuance.default_issuer'                     => 'marqeta',
            'cardissuance.issuers.marqeta.base_url'           => 'https://marqeta.test/v3',
            'cardissuance.issuers.marqeta.application_token'  => 'app',
            'cardissuance.issuers.marqeta.admin_access_token' => 'admin',
        ]);
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Http::fake([
            'marqeta.test/v3/cards/user/*'    => Http::response(['data' => []]),
            'marqeta.test/v3/cards/mq_card_1' => Http::response([
                'token'      => 'mq_card_1',
                'user_token' => (string) $owner->id,
                'state'      => 'TERMINATED',
                'last_four'  => '1234',
                'metadata'   => ['user_token' => (string) $other->id, 'user_id' => (string) $other->id],
            ]),
        ]);

        Sanctum::actingAs($owner, ['read', 'write', 'delete']);
        $this->getJson('/api/v1/cards/mq_card_1')
            ->assertOk()
            ->assertJsonPath('data.card_token', 'mq_card_1');

        Sanctum::actingAs($other, ['read', 'write', 'delete']);
        $this->getJson('/api/v1/cards/mq_card_1')
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'ERR_CARD_NOT_FOUND');
        $this->postJson('/api/v1/cards/mq_card_1/freeze')->assertStatus(404);

        Http::assertNotSent(fn (HttpRequest $r): bool => str_contains($r->url(), '/transitions'));
    });
});

describe('C50 the demo issuer does not issue cards in production', function (): void {
    beforeEach(function (): void {
        config(['cache.default' => 'array']);
        Cache::flush();
    });

    it('refuses to create a card or wallet pass in production', function (): void {
        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $adapter = new DemoCardIssuerAdapter();

            expect(fn () => $adapter->createCard('42', 'Jane Smith'))->toThrow(UnsupportedCardOperationException::class)
                ->and(fn () => $adapter->getProvisioningData('card_demo_x', WalletType::APPLE_PAY, 'device-1'))->toThrow(UnsupportedCardOperationException::class)
                ->and(Cache::get('user_cards:42'))->toBeNull();
        } finally {
            app()->detectEnvironment(fn () => $original);
        }
    });

    it('still issues demo cards and wallet passes outside production', function (): void {
        $adapter = new DemoCardIssuerAdapter();
        $card = $adapter->createCard('43', 'Jane Smith');

        expect($card->status)->toBe(CardStatus::ACTIVE)
            ->and($adapter->getProvisioningData($card->cardToken, WalletType::APPLE_PAY, 'device-1')->cardId)->toBe($card->cardToken)
            ->and(Cache::get('user_cards:43'))->toBe([$card->cardToken]);
    });

    // The issuer the Zelta template ships must fail closed with a clean error
    // in production on every card-creation path (REST create, REST provision,
    // GraphQL createCard), and still work outside production.
    it('answers card creation cleanly in production with the Zelta template issuer', function (): void {
        $template = (string) file_get_contents(base_path('.env.zelta.example'));
        $issuer = preg_match('/^CARD_ISSUER=(\S*)$/m', $template, $m) === 1 ? $m[1] : '';
        expect($issuer)->not->toBe('');
        config(['cardissuance.default_issuer' => $issuer]);

        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $original = app()->environment();
        app()->detectEnvironment(fn () => 'production');

        try {
            $this->postJson('/api/v1/cards', ['cardholder_name' => 'Jane Smith'])
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'ERR_CARD_005');

            $this->postJson('/api/v1/cards/provision', ['device_id' => 'device-1', 'wallet_type' => 'apple_pay'])
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'ERR_CARD_001');

            $graphql = $this->postJson('/graphql', [
                'query'     => 'mutation ($input: CreateCardInput!) { createCard(input: $input) { card_token } }',
                'variables' => ['input' => ['cardholder_id' => 'Jane Smith']],
            ])->assertOk();

            expect($graphql->json('data'))->toBeNull()
                ->and((string) $graphql->json('errors.0.message'))->toContain('Card issuing is not available');
        } finally {
            app()->detectEnvironment(fn () => $original);
        }

        expect(Cache::get("user_cards:{$user->id}"))->toBeNull();

        $this->postJson('/api/v1/cards', ['cardholder_name' => 'Jane Smith'])->assertCreated();
        expect(Cache::get("user_cards:{$user->id}"))->toHaveCount(1);
    });
});

describe('D09 JIT funding locks the account on its own connection', function (): void {
    it('checks and records spend inside a transaction on the account\'s connection', function (): void {
        Event::fake([AuthorizationApproved::class, AuthorizationDeclined::class]);
        config([
            'cardissuance.default_issuer'                  => 'marqeta',
            'cardissuance.jit_funding.max_auth_per_minute' => 100,
            'database.connections.tenant'                  => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'database.force_real_tenant_connection'        => true,
        ]);
        DB::purge('tenant');

        try {
            expect((new Account())->getConnectionName())->toBe('tenant');

            $schema = Schema::connection('tenant');
            $schema->create('accounts', function (Blueprint $t): void {
                $t->id();
                $t->uuid('uuid');
                $t->uuid('user_uuid')->nullable();
                $t->string('name')->nullable();
                $t->unsignedBigInteger('team_id')->nullable();
                $t->boolean('frozen')->default(false);
                $t->timestamps();
            });
            $schema->create('account_balances', function (Blueprint $t): void {
                $t->id();
                $t->uuid('account_uuid');
                $t->string('asset_code');
                $t->bigInteger('balance')->default(0);
                $t->timestamps();
            });
            $schema->create('assets', function (Blueprint $t): void {
                $t->string('code')->primary();
                $t->string('name')->nullable();
                $t->string('type')->nullable();
                $t->unsignedInteger('precision');
                $t->boolean('is_active')->default(true);
                $t->timestamps();
            });

            $userUuid = (string) Str::uuid();
            $accountUuid = (string) Str::uuid();
            DB::connection('tenant')->table('accounts')->insert(['uuid' => $accountUuid, 'user_uuid' => $userUuid, 'name' => 'Main']);
            DB::connection('tenant')->table('account_balances')->insert(['account_uuid' => $accountUuid, 'asset_code' => 'USDC', 'balance' => 50_000_000]);
            DB::connection('tenant')->table('assets')->insert(['code' => 'USDC', 'name' => 'USD Coin', 'type' => 'crypto', 'precision' => 6]);

            /** @var CardIssuerInterface&MockInterface $issuer */
            $issuer = Mockery::mock(CardIssuerInterface::class);
            $issuer->shouldReceive('getCard')->andReturn(new VirtualCard(
                cardToken: 'card_d09',
                last4: '4242',
                network: CardNetwork::VISA,
                status: CardStatus::ACTIVE,
                cardholderName: 'Holder',
                expiresAt: new DateTimeImmutable('+1 year'),
                metadata: ['user_id' => $userUuid],
            ));

            // Records the tenant-connection transaction level seen by the
            // spend-limit check and the spend record.
            $limits = new class () extends SpendLimitEnforcementService {
                /** @var array<string, int> */
                public array $levels = [];

                public function checkLimit(string $cardToken, float $amount): bool
                {
                    $this->levels['check'] = DB::connection('tenant')->transactionLevel();

                    return true;
                }

                public function recordSpend(string $cardToken, float $amount): void
                {
                    $this->levels['record'] = DB::connection('tenant')->transactionLevel();
                }
            };

            /** @var MetricsService&MockInterface $metrics */
            $metrics = Mockery::mock(MetricsService::class);
            $metrics->shouldIgnoreMissing();

            $result = (new JitFundingService($issuer, $limits, $metrics))->authorize(new AuthorizationRequest(
                authorizationId: 'auth_d09',
                cardToken: 'card_d09',
                amountCents: 2_000,
                currency: 'USD',
                merchantName: 'Coffee',
                merchantCategory: '5814',
            ));

            expect($result['approved'])->toBeTrue()
                ->and($limits->levels)->toBe(['check' => 1, 'record' => 1])
                ->and(DB::connection('tenant')->transactionLevel())->toBe(0);
        } finally {
            DB::purge('tenant');
        }
    });
});

describe('D10 unsigned FinCard webhooks are accepted only in local and testing', function (): void {
    it('rejects an unsigned webhook without a configured key outside local/testing', function (string $env): void {
        $original = app()->environment();
        app()->detectEnvironment(fn () => $env);

        try {
            expect((new FinCardWebhookVerifier(''))->verify('{}', 'anything'))->toBeFalse();
        } finally {
            app()->detectEnvironment(fn () => $original);
        }
    })->with(['staging', 'demo', 'production']);

    it('keeps the dev passthrough in local and testing', function (string $env): void {
        $original = app()->environment();
        app()->detectEnvironment(fn () => $env);

        try {
            expect((new FinCardWebhookVerifier(''))->verify('{}', 'anything'))->toBeTrue();
        } finally {
            app()->detectEnvironment(fn () => $original);
        }
    })->with(['local', 'testing']);

    it('answers 401 to a forged staging DEPOSIT and leaves the balance mirror unchanged', function (): void {
        config(['cardissuance.issuers.fincard.webhook_public_key' => '']);
        $this->app->instance(FinCardWebhookVerifier::class, new FinCardWebhookVerifier(''));
        $user = User::factory()->create();
        $account = ghsa385wCardsAccount($user, accountId: 'acc-victim', balanceCents: 100);

        $body = (string) json_encode(['eventType' => 'DEPOSIT', 'orderNo' => 'forged-1', 'data' => ['accountId' => 'acc-victim', 'balance' => '100000']]);

        $original = app()->environment();
        app()->detectEnvironment(fn () => 'staging');

        try {
            $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
                ->assertStatus(401);
        } finally {
            app()->detectEnvironment(fn () => $original);
        }

        expect((int) $account->fresh()?->balance_cents)->toBe(100);

        // Same request in testing (dev passthrough) is applied.
        $this->call('POST', '/api/v1/webhooks/fincard', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
            ->assertOk();
        expect((int) $account->fresh()?->balance_cents)->toBe(10000000);
    });
});

describe('Review K1/K2 FinCard order numbers and one funding account per user', function (): void {
    beforeEach(function (): void {
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->app->instance(FinCardClient::class, new FinCardClient(
            baseUrl: GHSA385W_FINCARD_BASE,
            tenantId: 't',
            orgId: 'o',
            userId: 'u',
            username: 'x',
            password: 'y',
        ));
        ghsa385wCardsFakeFinCard();
    });

    // K1: FinCard calls share the platform credential, so a raw client key
    // would put every user's orders in one namespace.
    it('sends FinCard an order number derived from the user and the Idempotency-Key, not the raw key', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        ghsa385wCardsCardholder($user);
        ghsa385wCardsAccount($user, accountId: 'acc-1');
        $key = 'Shared-Key-000000001';
        $expected = substr(hash('sha256', $user->id . '|' . $key), 0, 32);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/cards/fincard/open', ['card_type_id' => 111001, 'amount_cents' => 20000])
            ->assertCreated();

        Http::assertSent(fn (HttpRequest $r): bool => str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/card/v2/openCard')
            && $r['merchantOrderNo'] === $expected);
        expect(Card::query()->where('issuer_card_token', 'card-new')->value('merchant_order_no'))->toBe($expected);
    });

    it('gives two users different FinCard order numbers for the same Idempotency-Key', function (): void {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        ghsa385wCardsAccount($alice, accountId: 'acc-a');
        ghsa385wCardsAccount($bob, accountId: 'acc-b');
        $bobCard = ghsa385wCardsCard($bob, ghsa385wCardsCardholder($bob), accountId: 'acc-b');
        $bobCard->issuer_card_token = 'card-2';
        $bobCard->save();
        ghsa385wCardsCard($alice, ghsa385wCardsCardholder($alice), accountId: 'acc-a');
        $key = (string) Str::uuid();

        Sanctum::actingAs($alice, ['read', 'write', 'delete']);
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/cards/fincard/card-1/topup', ['amount_cents' => 1000])
            ->assertOk();
        Sanctum::actingAs($bob, ['read', 'write', 'delete']);
        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/cards/fincard/card-2/topup', ['amount_cents' => 1000])
            ->assertOk();

        $orderNos = Http::recorded(fn (HttpRequest $r): bool => str_ends_with((string) parse_url($r->url(), PHP_URL_PATH), '/card/deposit'))
            ->map(fn (array $pair): string => (string) $pair[0]['merchantOrderNo'])
            ->values()
            ->all();

        expect($orderNos)->toBe([
            substr(hash('sha256', $alice->id . '|' . $key), 0, 32),
            substr(hash('sha256', $bob->id . '|' . $key), 0, 32),
        ]);
    });

    // K2: one funding account per user, also under concurrent provisioning.
    it('refuses a second funding account row for the same user', function (): void {
        $user = User::factory()->create();
        ghsa385wCardsAccount($user, accountId: 'acc-1');

        expect(fn () => ghsa385wCardsAccount($user, accountId: 'acc-2'))
            ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
    });

    it('returns the account a concurrent request saved instead of creating a second one', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        ghsa385wCardsFakeFinCard([
            'sandbox.finhub.cloud/api/v2.1/fincard/virtual/account/create' => function () use ($user) {
                // Another request for the same user finishes provisioning
                // while this one waits for FinCard.
                ghsa385wCardsAccount($user, accountId: 'acc-concurrent', balanceCents: 0);

                return Http::response(['success' => true, 'data' => ['accountId' => 'acc-created']]);
            },
        ]);

        $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
            ->postJson('/api/v1/cards/account/deposit-address', ['coin_key' => 'USDT_TRC20'])
            ->assertCreated();

        expect(FinCardAccount::query()->where('user_id', $user->id)->pluck('fincard_account_id')->all())->toBe(['acc-concurrent']);
    });

    it('does not call FinCard while another request is provisioning the same user\'s account', function (): void {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $held = Cache::lock('fincard:account:provision:' . $user->id, 60);
        expect($held->get())->toBeTrue();

        try {
            $this->withHeader('Idempotency-Key', ghsa385wCardsIdem())
                ->postJson('/api/v1/cards/account/deposit-address', ['coin_key' => 'USDT_TRC20'])
                ->assertStatus(502)
                ->assertJsonPath('error.code', 'ERR_CARDS_013');
        } finally {
            $held->release();
        }

        expect(ghsa385wCardsSentTo('/account/create'))->toBeFalse()
            ->and(FinCardAccount::query()->where('user_id', $user->id)->exists())->toBeFalse();
    });

    it('refuses to add the unique index while a user has more than one funding account row', function (): void {
        $user = User::factory()->create();
        Schema::table('fincard_accounts', fn (Blueprint $table) => $table->dropUnique(['user_id']));
        ghsa385wCardsAccount($user, accountId: 'acc-1');
        ghsa385wCardsAccount($user, accountId: 'acc-2');

        $migration = require base_path('database/migrations/2026_11_19_000001_add_unique_user_id_to_fincard_accounts_table.php');

        expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'more than one fincard_accounts row');
    });
});
