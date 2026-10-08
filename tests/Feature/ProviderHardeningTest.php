<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\AI\MCP\Tools\SMS\SmsSendTool;
use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Enums\AuthorizationDecision;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Events\AuthorizationApproved;
use App\Domain\CardIssuance\Events\AuthorizationDeclined;
use App\Domain\CardIssuance\Services\JitFundingService;
use App\Domain\CardIssuance\Services\SpendLimitEnforcementService;
use App\Domain\CardIssuance\ValueObjects\AuthorizationRequest;
use App\Domain\CardIssuance\ValueObjects\VirtualCard;
use App\Domain\Exchange\Exceptions\RateProviderException;
use App\Domain\Exchange\Providers\FixerIoProvider;
use App\Domain\MachinePay\DataObjects\MppCredential;
use App\Domain\MachinePay\DataObjects\MppReceipt;
use App\Domain\MachinePay\Models\MppMonetizedResource;
use App\Domain\MachinePay\Services\MppSettlementService;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\SponsorshipRequestRejectedException;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Relayer\Services\GasStationService;
use App\Domain\Relayer\Services\WalletBalanceService;
use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsSendBudget;
use App\Domain\SMS\Services\SmsService;
use App\Domain\Wallet\Constants\EvmTokens;
use App\Domain\Wallet\Observers\BlockchainAddressObserver;
use App\Domain\Wallet\Services\HeliusWebhookSyncService;
use App\Domain\Wallet\Services\Send\Erc20Transfer;
use App\Domain\Wallet\Services\Send\UserOpCallDataBuilder;
use App\Domain\Wallet\Services\SponsorshipBudget;
use App\Domain\Wallet\Services\SponsorshipCostTracker;
use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Http\Middleware\SmsSendGateMiddleware;
use App\Infrastructure\Monitoring\MetricsService;
use App\Models\User;
use App\Support\SecretRedactor;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

/*
| Provider and infrastructure hardening:
|  - provider keys in URLs (Alchemy, Infura, custom RPC, Helius) never reach
|    exception messages, logs or command output on a transport failure;
|  - generic relayer sponsorship (REST and GraphQL) draws on the daily
|    sponsorship budget shared with the wallet send, and GraphQL
|    sponsorTransaction has the same per-user rate limit as REST;
|  - the demo card issuer declines every JIT authorization in production;
|  - POST /api/v1/sms/send: SMS_ENABLED switch, MPP-paid or authenticated
|    access, per-user limit and a global daily cap on unpaid sends.
*/

const PHV_ALCHEMY_KEY = 'phvAlchemyKey0123456789abcd';
const PHV_INFURA_ID = 'phvinfuraproject0123456789ab';
const PHV_HELIUS_KEY = 'phv-helius-key-0123456789abcdef';
const PHV_FIXER_KEY = 'phvFixerAccessKey0123456789';
const PHV_CUSTOM_RPC = 'https://rpc.example.test/phvCustomToken0123456789/';
const PHV_CUSTOM_TOKEN = 'phvCustomToken0123456789';
const PHV_SENDER = '0x4444444444444444444444444444444444444444';
const PHV_RECIPIENT = '0x5555555555555555555555555555555555555555';

// Limiter counters, sponsorship and SMS counters live in the cache store,
// which persists between tests when it is Redis (CI config).
beforeEach(function (): void {
    Cache::flush();
});

/**
 * Run the callback and collect every log record (message + context) written
 * meanwhile.
 *
 * @return array{0: mixed, 1: list<string>}
 */
function phvCaptureLogs(callable $callback): array
{
    $records = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$records): void {
        $records[] = $event->message . ' ' . (string) json_encode($event->context);
    });

    $result = $callback();

    return [$result, $records];
}

/**
 * @param  list<string>  $records
 */
function phvExpectNoSecret(string $secret, string $text, array $records = []): void
{
    expect($text)->not->toContain($secret);

    foreach ($records as $record) {
        expect($record)->not->toContain($secret);
    }
}

function phvBlockchainAddress(User $user, string $chain, string $address): BlockchainAddress
{
    /** @var BlockchainAddress $row */
    $row = BlockchainAddress::withoutEvents(fn () => BlockchainAddress::query()->create([
        'user_uuid'  => $user->uuid,
        'chain'      => $chain,
        'address'    => $address,
        'public_key' => '',
        'is_active'  => true,
    ]));

    return $row;
}

/*
|--------------------------------------------------------------------------
| P1 — provider key redaction
|--------------------------------------------------------------------------
*/

describe('provider key redaction on transport failures', function (): void {
    beforeEach(function (): void {
        config([
            'relayer.balance_checking.alchemy_api_key'    => PHV_ALCHEMY_KEY,
            'relayer.balance_checking.infura_project_id'  => PHV_INFURA_ID,
            'relayer.balance_checking.custom_rpc.polygon' => PHV_CUSTOM_RPC,
            'services.helius.api_key'                     => PHV_HELIUS_KEY,
            'services.helius.webhook_id'                  => 'phv-webhook-id',
        ]);
        Http::fake(['*' => Http::failedConnection()]);
    });

    it('masks Alchemy and Infura path keys even when they are not configured', function (string $url, string $secret): void {
        config([
            'relayer.balance_checking.alchemy_api_key'   => null,
            'relayer.balance_checking.infura_project_id' => null,
        ]);

        $redacted = SecretRedactor::redact("cURL error 6: Could not resolve host (see docs) for {$url}.");

        expect($redacted)->not->toContain($secret)
            ->and($redacted)->toContain(SecretRedactor::MASK);
    })->with([
        'alchemy' => ['https://polygon-mainnet.g.alchemy.com/v2/someAlchemyKey123', 'someAlchemyKey123'],
        'infura'  => ['https://mainnet.infura.io/v3/0123456789abcdef0123', '0123456789abcdef0123'],
    ]);

    it('keeps the balance provider key out of the exception and the log', function (string $provider, string $secret, string $hostHint): void {
        config(['relayer.balance_checking.provider' => $provider]);
        $service = new WalletBalanceService();

        $message = '';
        [, $records] = phvCaptureLogs(function () use ($service, &$message): void {
            try {
                $service->getBalance('0x' . str_repeat('a', 40), 'USDC', SupportedNetwork::POLYGON);
            } catch (RuntimeException $e) {
                $message = $e->getMessage();
                expect($e->getPrevious())->toBeNull();
            }
        });

        expect($message)->toStartWith('Failed to fetch balance:')
            ->and($message)->toContain($hostHint);
        expect(collect($records)->contains(fn (string $r): bool => str_contains($r, 'Balance RPC call failed')))->toBeTrue();
        phvExpectNoSecret($secret, $message, $records);
    })->with([
        'alchemy' => ['alchemy', PHV_ALCHEMY_KEY, 'alchemy.com'],
        'infura'  => ['infura', PHV_INFURA_ID, 'infura.io'],
        'custom'  => ['custom', PHV_CUSTOM_TOKEN, 'rpc.example.test'],
    ]);

    it('keeps the Fixer.io access key out of rate errors and logs', function (): void {
        $provider = new FixerIoProvider([
            'api_key'  => PHV_FIXER_KEY,
            'base_url' => 'https://api.fixer.io/v1',
            'debug'    => true,
        ]);

        $message = '';
        [$available, $records] = phvCaptureLogs(function () use ($provider, &$message): bool {
            try {
                $provider->getRate('USD', 'EUR');
            } catch (RateProviderException $e) {
                $message = $e->getMessage();
                expect($e->getPrevious())->toBeNull();
            }

            return $provider->isAvailable();
        });

        expect($available)->toBeFalse()
            ->and($message)->toContain('Fixer.io unreachable')
            ->and($message)->toContain('access_key=' . SecretRedactor::MASK);
        phvExpectNoSecret(PHV_FIXER_KEY, $message, $records);
    });

    it('keeps the Helius key out of webhook sync errors', function (): void {
        $message = '';

        [, $records] = phvCaptureLogs(function () use (&$message): void {
            try {
                app(HeliusWebhookSyncService::class)->addAddress('So1anaAddress1111111111111111111111111111111');
            } catch (RuntimeException $e) {
                $message = $e->getMessage();
                expect($e->getPrevious())->toBeNull();
            }
        });

        expect($message)->toContain('Helius webhook API unreachable');
        phvExpectNoSecret(PHV_HELIUS_KEY, $message, $records);
    });

    it('keeps the Helius key out of the address observer logs', function (string $event): void {
        $user = User::factory()->create();
        $address = phvBlockchainAddress($user, 'solana', 'So1anaAddress2222222222222222222222222222222');
        $observer = new BlockchainAddressObserver(app(HeliusWebhookSyncService::class));

        [, $records] = phvCaptureLogs(fn () => $observer->{$event}($address));

        expect(collect($records)->contains(fn (string $r): bool => str_contains($r, 'Solana webhook: Failed')))->toBeTrue();
        phvExpectNoSecret(PHV_HELIUS_KEY, '', $records);
    })->with(['created', 'deleted']);

    it('keeps the Alchemy key out of the EVM backfill output and log', function (): void {
        $user = User::factory()->create();
        phvBlockchainAddress($user, 'polygon', '0x' . str_repeat('b', 40));

        [$exitCode, $records] = phvCaptureLogs(fn (): int => Artisan::call('evm:backfill-transactions', ['--network' => 'polygon']));
        $output = Artisan::output();

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('alchemy.com/v2/' . SecretRedactor::MASK);
        phvExpectNoSecret(PHV_ALCHEMY_KEY, $output, $records);
    });

    it('keeps the Helius key out of the Solana backfill log', function (): void {
        $user = User::factory()->create();
        phvBlockchainAddress($user, 'solana', 'So1anaAddress3333333333333333333333333333333');

        [, $records] = phvCaptureLogs(fn (): int => Artisan::call('solana:backfill-transactions'));
        $output = Artisan::output();

        expect(collect($records)->contains(fn (string $r): bool => str_contains($r, 'Transaction history fetch error')))->toBeTrue();
        phvExpectNoSecret(PHV_HELIUS_KEY, $output, $records);
    });

    it('keeps the Helius key out of the reconcile command when Helius is unreachable', function (): void {
        [$exitCode, $records] = phvCaptureLogs(fn (): int => Artisan::call('solana:reconcile-helius'));
        $output = Artisan::output();

        expect($exitCode)->toBe(1)
            ->and($output)->toContain('Helius API unreachable');
        phvExpectNoSecret(PHV_HELIUS_KEY, $output, $records);
    });
});

/*
|--------------------------------------------------------------------------
| P2 — shared sponsorship budget
|--------------------------------------------------------------------------
*/

function phvTransferCallData(): string
{
    return UserOpCallDataBuilder::buildExecute(
        (string) EvmTokens::contractFor('USDC', 'polygon'),
        Erc20Transfer::encodeTransferCallData(PHV_RECIPIENT, '1500000'),
    );
}

function phvOwnSmartAccount(User $user): SmartAccount
{
    /** @var SmartAccount $account */
    $account = SmartAccount::query()->create([
        'user_id'         => $user->id,
        'owner_address'   => '0x' . str_repeat('6', 40),
        'account_address' => strtolower(PHV_SENDER),
        'network'         => 'polygon',
        'deployed'        => true,
        'nonce'           => 0,
        'pending_ops'     => 0,
    ]);

    return $account;
}

/**
 * @return array<string, mixed>
 */
function phvSponsorBody(): array
{
    return [
        'user_address' => PHV_SENDER,
        'call_data'    => phvTransferCallData(),
        'signature'    => '0x' . str_repeat('ab', 65),
        'network'      => 'polygon',
        'fee_token'    => 'USDC',
    ];
}

/**
 * @return array<string, mixed>
 */
function phvSponsorGraphQl(): array
{
    return [
        'query'     => 'mutation ($input: SponsorTransactionInput!) { sponsorTransaction(input: $input) }',
        'variables' => ['input' => ['network' => 'polygon', 'sender' => PHV_SENDER, 'data' => phvTransferCallData()]],
    ];
}

/**
 * @return GasStationService&MockInterface
 */
function phvMockGasStation(int $times): MockInterface
{
    /** @var GasStationService&MockInterface $gas */
    $gas = Mockery::mock(GasStationService::class);
    $gas->shouldReceive('sponsorTransaction')
        ->times($times)
        ->andReturn(['tx_hash' => null, 'user_op_hash' => '0x' . str_repeat('c', 64)]);

    app()->instance(GasStationService::class, $gas);

    return $gas;
}

describe('generic sponsorship shares the daily sponsorship budget', function (): void {
    beforeEach(function (): void {
        config([
            'relayer.generic_sponsor_enabled'         => true,
            'wallet.evm.enabled_networks'             => ['polygon', 'base', 'arbitrum'],
            'wallet.sponsorship.per_user_daily_limit' => 2,
            'wallet.sponsorship.global_daily_limit'   => 100,
            'wallet.sponsorship.daily_budget_usd'     => null,
        ]);
    });

    it('refuses GraphQL once REST has used up the per-user budget', function (): void {
        $user = User::factory()->create();
        phvOwnSmartAccount($user);
        phvMockGasStation(times: 2);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/relayer/sponsor', phvSponsorBody())->assertOk();
        $this->postJson('/api/v1/relayer/sponsor', phvSponsorBody())->assertOk();

        $response = $this->postJson('/graphql', phvSponsorGraphQl());

        expect((string) $response->json('errors.0.message'))->toContain("today's sponsored transaction limit")
            ->and($response->json('data.sponsorTransaction'))->toBeNull();
    });

    it('refuses REST once GraphQL has used up the per-user budget', function (): void {
        $user = User::factory()->create();
        phvOwnSmartAccount($user);
        phvMockGasStation(times: 2);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        expect($this->postJson('/graphql', phvSponsorGraphQl())->json('errors'))->toBeNull();
        expect($this->postJson('/graphql', phvSponsorGraphQl())->json('errors'))->toBeNull();

        $this->postJson('/api/v1/relayer/sponsor', phvSponsorBody())
            ->assertStatus(429)
            ->assertJsonPath('error.code', SponsorshipRequestRejectedException::CODE_DAILY_LIMIT_REACHED);
    });

    it('uses the same counters as the wallet send', function (): void {
        $user = User::factory()->create();
        phvOwnSmartAccount($user);
        phvMockGasStation(times: 1);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/relayer/sponsor', phvSponsorBody())->assertOk();

        expect((int) Cache::get(SponsorshipBudget::perUserCountCacheKey($user->id)))->toBe(1)
            ->and((int) Cache::get(SponsorshipCostTracker::globalSendCountCacheKey()))->toBe(1);

        // One wallet send (same budget) uses up the rest of the user's day.
        expect(app(SponsorshipBudget::class)->consume($user->id))->toBeNull();

        $this->postJson('/api/v1/relayer/sponsor', phvSponsorBody())
            ->assertStatus(429)
            ->assertJsonPath('error.code', SponsorshipRequestRejectedException::CODE_DAILY_LIMIT_REACHED);
    });

    it('refuses both paths once the global budget is used up', function (): void {
        config(['wallet.sponsorship.global_daily_limit' => 1]);
        $first = User::factory()->create();
        phvOwnSmartAccount($first);
        phvMockGasStation(times: 1);

        Sanctum::actingAs($first, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/relayer/sponsor', phvSponsorBody())->assertOk();

        $second = User::factory()->create();
        SmartAccount::query()->where('user_id', $first->id)->update(['user_id' => $second->id]);
        Sanctum::actingAs($second, ['read', 'write', 'delete']);

        $response = $this->postJson('/graphql', phvSponsorGraphQl());
        expect((string) $response->json('errors.0.message'))->toContain('temporarily paused');

        $this->postJson('/api/v1/relayer/sponsor', phvSponsorBody())
            ->assertStatus(429)
            ->assertJsonPath('error.code', SponsorshipRequestRejectedException::CODE_TEMPORARILY_UNAVAILABLE);
    });

    it('refuses once the daily USD budget is spent', function (): void {
        config(['wallet.sponsorship.daily_budget_usd' => '1']);
        Cache::put(SponsorshipCostTracker::budgetCacheKey(), 2_000_000, now()->addDay()); // $2 spent

        $user = User::factory()->create();
        phvOwnSmartAccount($user);
        phvMockGasStation(times: 0);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/relayer/sponsor', phvSponsorBody())
            ->assertStatus(429)
            ->assertJsonPath('error.code', SponsorshipRequestRejectedException::CODE_TEMPORARILY_UNAVAILABLE);
    });

    it('does not consume budget for a request the policy rejects', function (): void {
        phvOwnSmartAccount(User::factory()->create());
        $other = User::factory()->create();
        phvMockGasStation(times: 0);
        Sanctum::actingAs($other, ['read', 'write', 'delete']);

        $this->postJson('/api/v1/relayer/sponsor', phvSponsorBody())->assertForbidden();

        expect(Cache::get(SponsorshipBudget::perUserCountCacheKey($other->id)))->toBeNull()
            ->and(Cache::get(SponsorshipCostTracker::globalSendCountCacheKey()))->toBeNull();
    });

    it('leaves the global count alone for a user over the per-user limit', function (): void {
        config([
            'wallet.sponsorship.per_user_daily_limit' => 1,
            'wallet.sponsorship.global_daily_limit'   => 3,
        ]);
        $budget = app(SponsorshipBudget::class);

        expect($budget->consume(1001, 'relayer.sponsor'))->toBeNull();

        foreach (range(1, 5) as $ignored) {
            expect($budget->consume(1001, 'relayer.sponsor'))->toBe(SponsorshipBudget::DENIED_USER_LIMIT);
        }

        expect((int) Cache::get(SponsorshipCostTracker::globalSendCountCacheKey()))->toBe(1)
            ->and((int) Cache::get(SponsorshipBudget::perUserCountCacheKey(1001)))->toBe(1)
            ->and($budget->consume(2002, 'wallet.send'))->toBeNull()
            ->and($budget->consume(3003, 'graphql.sponsorTransaction'))->toBeNull()
            ->and($budget->consume(4004, 'wallet.send'))->toBe(SponsorshipBudget::DENIED_GLOBAL_LIMIT)
            ->and((int) Cache::get(SponsorshipCostTracker::globalSendCountCacheKey()))->toBe(3)
            ->and((int) Cache::get(SponsorshipBudget::perUserCountCacheKey(4004)))->toBe(0);
    });

    it('limits GraphQL sponsorTransaction per user like REST transaction.rate_limit:relayer', function (): void {
        config([
            'rate_limiting.enabled'                   => true,
            'rate_limiting.force_in_tests'            => true,
            'wallet.sponsorship.per_user_daily_limit' => 1000,
        ]);
        $user = User::factory()->create();
        phvOwnSmartAccount($user);
        phvMockGasStation(times: 30);
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        for ($i = 0; $i < 30; $i++) {
            expect($this->postJson('/graphql', phvSponsorGraphQl())->json('errors'))->toBeNull();
        }

        $response = $this->postJson('/graphql', phvSponsorGraphQl());

        expect((string) $response->json('errors.0.message'))->toContain('Rate limit')
            ->and($response->json('data.sponsorTransaction'))->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| P3 — card JIT funding with the demo issuer
|--------------------------------------------------------------------------
*/

function phvAuthRequest(int $amountCents = 500): AuthorizationRequest
{
    return new AuthorizationRequest(
        authorizationId: 'auth_' . Str::random(10),
        cardToken: 'card_phv_001',
        amountCents: $amountCents,
        currency: 'USD',
        merchantName: 'Coffee',
        merchantCategory: '5814',
    );
}

/**
 * @return CardIssuerInterface&MockInterface
 */
function phvCardIssuer(User $user): MockInterface
{
    /** @var CardIssuerInterface&MockInterface $issuer */
    $issuer = Mockery::mock(CardIssuerInterface::class);
    $issuer->shouldReceive('getCard')->andReturn(new VirtualCard(
        cardToken: 'card_phv_001',
        last4: '4242',
        network: CardNetwork::VISA,
        status: CardStatus::ACTIVE,
        cardholderName: 'Test Holder',
        expiresAt: new DateTimeImmutable('+1 year'),
        metadata: ['user_id' => $user->uuid],
    ));

    return $issuer;
}

function phvJitService(CardIssuerInterface $issuer): JitFundingService
{
    /** @var MetricsService&MockInterface $metrics */
    $metrics = Mockery::mock(MetricsService::class);
    $metrics->shouldIgnoreMissing();

    return new JitFundingService($issuer, app(SpendLimitEnforcementService::class), $metrics);
}

describe('card JIT funding with the demo issuer', function (): void {
    beforeEach(function (): void {
        Event::fake([AuthorizationApproved::class, AuthorizationDeclined::class]);
        config(['cardissuance.default_issuer' => 'demo', 'cardissuance.jit_funding.max_auth_per_minute' => 100]);
    });

    it('declines every authorization in production without issuing a hold', function (int $amountCents): void {
        $this->app->detectEnvironment(fn (): string => 'production');

        /** @var CardIssuerInterface&MockInterface $issuer */
        $issuer = Mockery::mock(CardIssuerInterface::class);
        $issuer->shouldNotReceive('getCard');

        $result = phvJitService($issuer)->authorize(phvAuthRequest($amountCents));

        expect($result['approved'])->toBeFalse()
            ->and($result['hold_id'])->toBeNull()
            ->and($result['decision'])->toBe(AuthorizationDecision::DECLINED_INSUFFICIENT_FUNDS);
        Event::assertDispatched(AuthorizationDeclined::class);
        Event::assertNotDispatched(AuthorizationApproved::class);
    })->with(['one cent' => [1], 'five dollars' => [500], 'large amount' => [99_999]]);

    it('keeps demo approvals outside production', function (string $environment): void {
        $this->app->detectEnvironment(fn (): string => $environment);

        $result = phvJitService(phvCardIssuer(User::factory()->create()))->authorize(phvAuthRequest());

        expect($result['approved'])->toBeTrue()
            ->and($result['hold_id'])->toStartWith('hold_');
    })->with(['local', 'testing', 'demo']);

    it('still funds live issuers from the ledger balance in production', function (): void {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['cardissuance.default_issuer' => 'marqeta']);

        DB::table('assets')->updateOrInsert(
            ['code' => 'USDC'],
            ['name' => 'USD Coin', 'type' => 'crypto', 'precision' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        );
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        AccountBalance::query()->updateOrCreate(
            ['account_uuid' => $account->uuid, 'asset_code' => 'USDC'],
            ['balance' => 50_000_000],
        );

        $result = phvJitService(phvCardIssuer($user))->authorize(phvAuthRequest(1_000));

        expect($result['approved'])->toBeTrue()
            ->and($result['hold_id'])->toStartWith('hold_');
    });

    it('answers a declined authorization on the webhook in production', function (): void {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'cardissuance.issuers.marqeta.webhook_username' => 'marqeta',
            'cardissuance.issuers.marqeta.webhook_password' => 'phv-s3cret-pass',
            'cardissuance.issuers.marqeta.webhook_secret'   => null,
            'cardissuance.issuers.marqeta.hmac_secret'      => null,
        ]);

        $this->withHeader('Authorization', 'Basic ' . base64_encode('marqeta:phv-s3cret-pass'))
            ->postJson('/api/webhooks/card-issuer/authorization', [
                'authorization_id' => 'auth_phv_webhook',
                'card_token'       => 'card_phv_001',
                'amount'           => 500,
                'currency'         => 'USD',
                'merchant_name'    => 'Coffee',
            ])
            ->assertOk()
            ->assertJsonPath('approved', false)
            ->assertJsonPath('hold_id', null);
    });
});

/*
|--------------------------------------------------------------------------
| P4 — SMS send access
|--------------------------------------------------------------------------
*/

/**
 * @return SmsService&MockInterface
 */
function phvMockSms(int $times, ?Closure $withArgs = null): MockInterface
{
    /** @var SmsService&MockInterface $sms */
    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('send')
        ->times($times)
        ->withArgs($withArgs ?? static fn (): bool => true)
        ->andReturn([
            'message_id'  => 'phv-msg-1',
            'status'      => 'sent',
            'parts'       => 1,
            'destination' => '+37069912345',
            'price_usdc'  => '48438',
        ]);

    app()->instance(SmsService::class, $sms);

    return $sms;
}

/**
 * @return MppSettlementService&MockInterface
 */
function phvMockSettlement(): MockInterface
{
    /** @var MppSettlementService&MockInterface $settlement */
    $settlement = Mockery::mock(MppSettlementService::class);
    app()->instance(MppSettlementService::class, $settlement);

    return $settlement;
}

function phvMonetizeSmsSend(): void
{
    MppMonetizedResource::query()->create([
        'method'          => 'POST',
        'path'            => 'api/v1/sms/send',
        'amount_cents'    => 5,
        'currency'        => 'USD',
        'available_rails' => ['stripe'],
        'description'     => 'SMS send',
        'is_active'       => true,
    ]);
}

function phvPaymentHeader(): string
{
    return 'Payment ' . (new MppCredential(
        challengeId: 'ch_phv_test',
        rail: 'stripe',
        proofOfPayment: ['spt' => 'spt_phv'],
        payerIdentifier: 'agent_phv',
        timestamp: gmdate('Y-m-d\TH:i:s\Z'),
    ))->toBase64Url();
}

/**
 * @return SmsPricingService&MockInterface
 */
function phvMockSmsPrice(string $amountUsdc = '48438'): MockInterface
{
    /** @var SmsPricingService&MockInterface $pricing */
    $pricing = Mockery::mock(SmsPricingService::class);
    $pricing->shouldReceive('getPriceForNumber')->andReturn([
        'amount_usdc'  => $amountUsdc,
        'rate_eur'     => '0.0390',
        'country_code' => 'LT',
        'parts'        => 1,
    ]);

    app()->instance(SmsPricingService::class, $pricing);

    return $pricing;
}

/**
 * @return array{to: string, message: string}
 */
function phvSmsBody(string $to = '+37069912345'): array
{
    return ['to' => $to, 'message' => 'Hello from the provider hardening test'];
}

describe('SMS send access', function (): void {
    beforeEach(function (): void {
        config([
            'sms.enabled'                        => true,
            'sms.global_daily_limit'             => 500,
            'sms.rate_limit.per_user_per_minute' => 50,
            'sms.rate_limit.per_user_per_day'    => 50,
            'machinepay.enabled'                 => false,
        ]);
    });

    it('requires a Sanctum token while MPP is off', function (): void {
        phvMockSms(times: 0);

        $this->postJson('/api/v1/sms/send', phvSmsBody())->assertUnauthorized();
    });

    it('sends for a bearer token while MPP is off', function (): void {
        phvMockSms(times: 1);
        $token = User::factory()->create()->createToken('phv', ['read', 'write', 'delete'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/sms/send', phvSmsBody())
            ->assertOk()
            ->assertJsonPath('data.message_id', 'phv-msg-1');

        expect((int) Cache::get(SmsSendGateMiddleware::globalUnpaidCountCacheKey()))->toBe(1);
    });

    it('answers the MPP challenge without a credential while MPP is on', function (): void {
        config(['machinepay.enabled' => true]);
        phvMonetizeSmsSend();
        phvMockSms(times: 0);

        $this->postJson('/api/v1/sms/send', phvSmsBody())
            ->assertStatus(402)
            ->assertHeader('X-Payment-Protocol', 'mpp');
    });

    it('sends through the MPP gate with a Payment credential', function (): void {
        config(['machinepay.enabled' => true]);
        phvMonetizeSmsSend();
        $settlement = phvMockSettlement();
        $settlement->shouldReceive('settle')->once()->andReturn(new MppReceipt(
            receiptId: 'rcpt_phv',
            challengeId: 'ch_phv_test',
            rail: 'stripe',
            settlementReference: 'pi_phv',
            settledAt: gmdate('c'),
            amountCents: 5,
            currency: 'USD',
        ));
        phvMockSms(
            times: 1,
            withArgs: fn (string $to, string $from, string $message, array $paymentMeta): bool => ($paymentMeta['receipt_id'] ?? null) === 'rcpt_phv',
        );

        $this->withHeader('Authorization', phvPaymentHeader())
            ->postJson('/api/v1/sms/send', phvSmsBody())
            ->assertOk()
            ->assertHeader('Payment-Receipt');

        // Paid sends do not count against the unpaid cap.
        expect(Cache::get(SmsSendGateMiddleware::globalUnpaidCountCacheKey()))->toBeNull();
    });

    it('uses the free per-user path for a bearer token while MPP is on', function (): void {
        config(['machinepay.enabled' => true]);
        phvMonetizeSmsSend();
        phvMockSettlement()->shouldNotReceive('settle');
        phvMockSms(times: 1);
        $token = User::factory()->create()->createToken('phv', ['read', 'write', 'delete'])->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/sms/send', phvSmsBody())
            ->assertOk();

        expect($response->headers->has('Payment-Receipt'))->toBeFalse()
            ->and((int) Cache::get(SmsSendGateMiddleware::globalUnpaidCountCacheKey()))->toBe(1);
    });

    it('requires a token while MPP is on but the route is not monetized', function (): void {
        config(['machinepay.enabled' => true]);
        phvMockSettlement()->shouldNotReceive('settle');
        phvMockSms(times: 0);

        $this->postJson('/api/v1/sms/send', phvSmsBody())->assertUnauthorized();
        $this->withHeader('Authorization', phvPaymentHeader())
            ->postJson('/api/v1/sms/send', phvSmsBody())
            ->assertUnauthorized();
    });

    it('enforces the global daily cap on unpaid sends', function (): void {
        config(['sms.global_daily_limit' => 2]);
        phvMockSms(times: 2);

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        // A request that fails validation is not counted.
        $this->postJson('/api/v1/sms/send', phvSmsBody('invalid'))->assertStatus(422);
        $this->postJson('/api/v1/sms/send', phvSmsBody())->assertOk();

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/v1/sms/send', phvSmsBody())->assertOk();

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/v1/sms/send', phvSmsBody())
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'ERR_SMS_001')
            ->assertHeader('Retry-After');

        expect((int) Cache::get(SmsSendGateMiddleware::globalUnpaidCountCacheKey()))->toBe(2);
    });

    it('keeps the per-user limit on the unpaid path', function (): void {
        config(['sms.rate_limit.per_user_per_minute' => 1]);
        phvMockSms(times: 1);

        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/v1/sms/send', phvSmsBody())->assertOk();
        $this->postJson('/api/v1/sms/send', phvSmsBody())->assertStatus(429);
    });

    it('answers 503 on every path while SMS_ENABLED is off', function (bool $mpp, string $auth): void {
        config(['sms.enabled' => false, 'machinepay.enabled' => $mpp]);
        phvMonetizeSmsSend();
        phvMockSettlement()->shouldNotReceive('settle');
        phvMockSms(times: 0);

        $request = $this;
        if ($auth === 'bearer') {
            $token = User::factory()->create()->createToken('phv', ['read', 'write', 'delete'])->plainTextToken;
            $request = $this->withHeader('Authorization', 'Bearer ' . $token);
        } elseif ($auth === 'payment') {
            $request = $this->withHeader('Authorization', phvPaymentHeader());
        }

        $request->postJson('/api/v1/sms/send', phvSmsBody())
            ->assertStatus(503)
            ->assertJsonPath('error', 'SMS service is not enabled');
    })->with([
        'MPP off, no token'          => [false, 'none'],
        'MPP off, bearer token'      => [false, 'bearer'],
        'MPP on, no credential'      => [true, 'none'],
        'MPP on, Payment credential' => [true, 'payment'],
        'MPP on, bearer token'       => [true, 'bearer'],
    ]);

    it('lets a request already paid on a protocol subdomain through', function (string $attribute, array $payment): void {
        $request = Request::create('/api/v1/sms/send', 'POST');
        $request->attributes->set($attribute, $payment);

        $response = app(SmsSendGateMiddleware::class)
            ->handle($request, fn (): Illuminate\Http\JsonResponse => response()->json(['passed' => true]));

        expect($response->getStatusCode())->toBe(200)
            ->and(Cache::get(SmsSendGateMiddleware::globalUnpaidCountCacheKey()))->toBeNull();

        // Without a settled payment the unpaid path still needs a user.
        expect(fn () => app(SmsSendGateMiddleware::class)->handle(
            Request::create('/api/v1/sms/send', 'POST'),
            fn (): Illuminate\Http\JsonResponse => response()->json(['passed' => true]),
        ))->toThrow(AuthenticationException::class);
    })->with([
        'MPP, $0.05'            => ['mpp_payment', ['receipt_id' => 'rcpt_phv', 'amount_cents' => 5, 'currency' => 'USD']],
        'x402, $0.01 (minimum)' => ['x402_payment', ['transaction' => '0xphv', 'amount' => '0.01']],
    ]);

    it('applies the unpaid rules to a payment below the minimum paid price', function (string $attribute, array $payment): void {
        config(['sms.global_daily_limit' => 0]);
        $reached = false;
        $next = function () use (&$reached): Illuminate\Http\JsonResponse {
            $reached = true;

            return response()->json(['passed' => true]);
        };

        $request = Request::create('/api/v1/sms/send', 'POST');
        $request->attributes->set($attribute, $payment);

        // No user: the unpaid path needs a token.
        expect(fn () => app(SmsSendGateMiddleware::class)->handle($request, $next))
            ->toThrow(AuthenticationException::class);

        // With a user, the global cap on unpaid sends applies.
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $request = Request::create('/api/v1/sms/send', 'POST');
        $request->attributes->set($attribute, $payment);

        $response = app(SmsSendGateMiddleware::class)->handle($request, $next);

        expect($response->getStatusCode())->toBe(429)
            ->and($reached)->toBeFalse();
    })->with([
        'x402, 1 atomic unit'   => ['x402_payment', ['transaction' => '0xphv', 'amount' => '0.000001']],
        'x402, free'            => ['x402_payment', ['transaction' => '0xphv', 'amount' => '0']],
        'MPP, zero'             => ['mpp_payment', ['receipt_id' => 'rcpt_phv', 'amount_cents' => 0, 'currency' => 'USD']],
        'MPP, unknown currency' => ['mpp_payment', ['receipt_id' => 'rcpt_phv', 'amount_cents' => 500, 'currency' => 'XYZ']],
        'MPP, no amount'        => ['mpp_payment', ['receipt_id' => 'rcpt_phv']],
    ]);

    it('does not offer an MPP resource priced below the minimum paid price', function (): void {
        config(['machinepay.enabled' => true, 'sms.min_paid_price_usdc' => 100000]);
        phvMonetizeSmsSend(); // $0.05, below the $0.10 minimum
        phvMockSettlement()->shouldNotReceive('settle');
        phvMockSms(times: 0);

        $this->withHeader('Authorization', phvPaymentHeader())
            ->postJson('/api/v1/sms/send', phvSmsBody())
            ->assertUnauthorized();
        $this->postJson('/api/v1/sms/send', phvSmsBody())->assertUnauthorized();
    });

    it('does not let an x402 price row written by a regular user open the route', function (): void {
        config(['x402.enabled' => true]);

        Sanctum::actingAs(User::factory()->withPersonalTeam()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/v1/x402/endpoints', ['method' => 'POST', 'path' => '/v1/sms/send', 'price' => '0.000001'])
            ->assertForbidden();

        $json = $this->postJson('/graphql', [
            'query' => 'mutation { createX402MonetizedEndpoint(input: {method: "POST", path: "/api/v1/sms/send", price: "0"}) { id } }',
        ])->json();

        expect($json['errors'][0]['message'] ?? null)->toBe('You are not authorized to manage monetized endpoints.')
            ->and(X402MonetizedEndpoint::query()->count())->toBe(0);
    });

    it('counts MCP sms.send against the same per-user and global limits', function (): void {
        config(['sms.global_daily_limit' => 2, 'sms.rate_limit.per_user_per_day' => 1]);
        phvMockSms(times: 2);
        phvMockSmsPrice();

        $first = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();

        // One REST send uses up the first user's day.
        Sanctum::actingAs($first, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/sms/send', phvSmsBody())->assertOk();

        $this->actingAs($first);
        $refused = app(SmsSendTool::class)->execute(['to' => '+37069912345', 'message' => 'hello']);
        expect($refused->isSuccess())->toBeFalse()
            ->and($refused->getError())->toContain("Today's SMS sending limit has been reached");

        // A second user sends through MCP; the global cap is then reached.
        $this->actingAs($second);
        expect(app(SmsSendTool::class)->execute(['to' => '+37069912345', 'message' => 'hello'])->isSuccess())->toBeTrue();

        $this->actingAs($third);
        expect(app(SmsSendTool::class)->execute(['to' => '+37069912345', 'message' => 'hello'])->isSuccess())->toBeFalse();

        Sanctum::actingAs($third, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/sms/send', phvSmsBody())
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'ERR_SMS_001');

        expect((int) Cache::get(SmsSendBudget::globalCountCacheKey()))->toBe(2)
            ->and((int) Cache::get(SmsSendBudget::userCountCacheKey($first->id)))->toBe(1)
            ->and((int) Cache::get(SmsSendBudget::userCountCacheKey($third->id)))->toBe(0);
    });

    it('refuses MCP sms.send without a signed-in user', function (): void {
        phvMockSms(times: 0);
        phvMockSmsPrice();

        $result = app(SmsSendTool::class)->execute(['to' => '+37069912345', 'message' => 'hello']);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe('SMS sending requires a signed-in user')
            ->and(Cache::get(SmsSendBudget::globalCountCacheKey()))->toBeNull();
    });

    it('hands the MCP unit back when the send fails', function (): void {
        /** @var SmsService&MockInterface $sms */
        $sms = Mockery::mock(SmsService::class);
        $sms->shouldReceive('send')->once()->andThrow(new RuntimeException('provider down'));
        app()->instance(SmsService::class, $sms);
        phvMockSmsPrice();
        $user = User::factory()->create();
        $this->actingAs($user);

        $result = app(SmsSendTool::class)->execute(['to' => '+37069912345', 'message' => 'hello']);

        expect($result->isSuccess())->toBeFalse()
            ->and((int) Cache::get(SmsSendBudget::globalCountCacheKey()))->toBe(0)
            ->and((int) Cache::get(SmsSendBudget::userCountCacheKey($user->id)))->toBe(0);
    });
});
