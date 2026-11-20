<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\AI\MCP\Tools\SMS\SmsSendTool;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\Auth\Services\PrivyJwtVerifier;
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
use App\Domain\Cgo\Mail\CgoNotificationReceived;
use App\Domain\Newsletter\Exceptions\SubscriptionThrottledException;
use App\Domain\Newsletter\Mail\SubscriberConfirmation;
use App\Domain\Newsletter\Mail\SubscriberWelcome;
use App\Domain\Newsletter\Models\Subscriber;
use App\Domain\Newsletter\Services\SubscriberEmailService;
use App\Domain\Relayer\Contracts\BundlerInterface;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\RpcException;
use App\Domain\Relayer\Exceptions\SponsorshipRequestRejectedException;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Relayer\Services\EthRpcClient;
use App\Domain\Relayer\Services\GasStationService;
use App\Domain\Relayer\Services\PimlicoBundlerService;
use App\Domain\Relayer\Services\PimlicoPaymasterService;
use App\Domain\Relayer\Services\SmartAccountService;
use App\Domain\Relayer\Services\SponsorshipRequestValidator;
use App\Domain\SMS\Services\SmsPricingService;
use App\Domain\SMS\Services\SmsSendBudget;
use App\Domain\SMS\Services\SmsService;
use App\Domain\User\Models\UserInvitation;
use App\Domain\Wallet\Constants\EvmTokens;
use App\Domain\Wallet\Exceptions\SolanaRpcException;
use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\Send\Erc20Transfer;
use App\Domain\Wallet\Services\Send\EvmUserOpPreparer;
use App\Domain\Wallet\Services\Send\EvmUserOpSubmitter;
use App\Domain\Wallet\Services\Send\HeliusRpcClient;
use App\Domain\Wallet\Services\Send\UserOpCallDataBuilder;
use App\Infrastructure\Monitoring\MetricsService;
use App\Models\User;
use App\Support\SecretRedactor;
use Firebase\JWT\JWT;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery\MockInterface;

/*
| Production hardening additions:
|  - provider keys never reach send records, API payloads or logs (Pimlico,
|    Helius) and an empty PIMLICO_BUNDLER_URL falls back to the per-chain
|    endpoint;
|  - SMS sending needs a user, has a per-user limit and honours SMS_ENABLED
|    on every path (REST, MCP tool, service);
|  - newsletter / CGO sign-ups are double opt-in and throttled;
|  - card JIT funding uses the real balance + holds for live issuers and the
|    issuer webhooks fail closed;
|  - generic relayer sponsorship is off in production and bound to the
|    caller's own stablecoin transfers when on;
|  - API registration can be closed or made invite-only on every path that
|    creates a user.
*/

const PHA_ENV_KEYS = [
    'APP_ENV',
    'SMS_ENABLED',
    'RELAYER_GENERIC_SPONSOR_ENABLED',
    'REGISTRATION_API_ENABLED',
    'REGISTRATION_API_INVITE_ONLY',
];

// Rate-limiter counters, cached Privy signing keys and SMS dedup keys live in
// the cache store, which persists between tests when it is Redis (CI config).
beforeEach(function (): void {
    Cache::flush();
});

/**
 * Evaluate a config file with the given environment variables (null =
 * unset) and restore the process environment afterwards.
 *
 * @param  array<string, string|null>  $env
 * @return array<string, mixed>
 */
function phaConfigUnderEnv(string $file, array $env): array
{
    $env = array_merge(array_fill_keys(PHA_ENV_KEYS, null), $env);
    $saved = [];

    foreach ($env as $key => $value) {
        $saved[$key] = [
            'has_server' => array_key_exists($key, $_SERVER),
            'server'     => $_SERVER[$key] ?? null,
            'has_env'    => array_key_exists($key, $_ENV),
            'env'        => $_ENV[$key] ?? null,
            'getenv'     => getenv($key),
        ];

        if ($value === null) {
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    try {
        /** @var array<string, mixed> $config */
        $config = require base_path("config/{$file}.php");

        return $config;
    } finally {
        foreach ($saved as $key => $previous) {
            if ($previous['has_server']) {
                $_SERVER[$key] = $previous['server'];
            } else {
                unset($_SERVER[$key]);
            }
            if ($previous['has_env']) {
                $_ENV[$key] = $previous['env'];
            } else {
                unset($_ENV[$key]);
            }
            if ($previous['getenv'] === false) {
                putenv($key);
            } else {
                putenv("{$key}={$previous['getenv']}");
            }
        }
    }
}

describe('secure defaults for the new flags', function (): void {
    it('turns SMS, generic sponsorship and API registration off under APP_ENV=production', function (?string $appEnv): void {
        $env = ['APP_ENV' => $appEnv];

        expect(phaConfigUnderEnv('sms', $env)['enabled'])->toBeFalse()
            ->and(phaConfigUnderEnv('relayer', $env)['generic_sponsor_enabled'])->toBeFalse()
            ->and(phaConfigUnderEnv('registration', $env)['api_enabled'])->toBeFalse()
            ->and(phaConfigUnderEnv('registration', $env)['api_invite_only'])->toBeFalse();
    })->with([
        'production'    => ['production'],
        'APP_ENV unset' => [null],
        // Any value outside local/testing/demo is treated as production.
        'staging' => ['staging'],
    ]);

    it('keeps them on for local, testing and demo', function (string $appEnv): void {
        $env = ['APP_ENV' => $appEnv];

        expect(phaConfigUnderEnv('sms', $env)['enabled'])->toBeTrue()
            ->and(phaConfigUnderEnv('relayer', $env)['generic_sponsor_enabled'])->toBeTrue()
            ->and(phaConfigUnderEnv('registration', $env)['api_enabled'])->toBeTrue();
    })->with(['local', 'testing', 'demo']);

    it('lets an explicit env value win', function (): void {
        $production = [
            'APP_ENV'                         => 'production',
            'SMS_ENABLED'                     => 'true',
            'RELAYER_GENERIC_SPONSOR_ENABLED' => 'true',
            'REGISTRATION_API_ENABLED'        => 'true',
            'REGISTRATION_API_INVITE_ONLY'    => 'true',
        ];

        expect(phaConfigUnderEnv('sms', $production)['enabled'])->toBeTrue()
            ->and(phaConfigUnderEnv('relayer', $production)['generic_sponsor_enabled'])->toBeTrue()
            ->and(phaConfigUnderEnv('registration', $production)['api_enabled'])->toBeTrue()
            ->and(phaConfigUnderEnv('registration', $production)['api_invite_only'])->toBeTrue();

        $demo = [
            'APP_ENV'                         => 'demo',
            'SMS_ENABLED'                     => 'false',
            'RELAYER_GENERIC_SPONSOR_ENABLED' => 'false',
            'REGISTRATION_API_ENABLED'        => 'false',
        ];

        expect(phaConfigUnderEnv('sms', $demo)['enabled'])->toBeFalse()
            ->and(phaConfigUnderEnv('relayer', $demo)['generic_sponsor_enabled'])->toBeFalse()
            ->and(phaConfigUnderEnv('registration', $demo)['api_enabled'])->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| B1 — provider key redaction
|--------------------------------------------------------------------------
*/

const PHA_PIMLICO_KEY = 'pim_LEAKCHECK9f8e7d6c5b4a';

/**
 * Run the callback and collect every log record written meanwhile.
 *
 * @template T
 *
 * @param  callable(): T  $callback
 * @return array{0: T, 1: list<string>} the callback result and one
 *                                      "message + JSON context" line per record
 */
function phaCaptureLogs(callable $callback): array
{
    $records = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$records): void {
        $records[] = $event->message . ' ' . (string) json_encode($event->context);
    });

    $result = $callback();

    return [$result, $records];
}

describe('provider key redaction', function (): void {
    beforeEach(function (): void {
        config([
            'relayer.pimlico.api_key'     => PHA_PIMLICO_KEY,
            'relayer.pimlico.bundler_url' => null,
            'relayer.pimlico.retry_count' => 1,
            'wallet.evm.enabled_networks' => ['polygon', 'base', 'arbitrum'],
        ]);
    });

    it('redacts api keys, bearer tokens and configured secrets', function (): void {
        config(['services.helius.api_key' => 'helius-secret-value-123']);

        $redacted = SecretRedactor::redact(
            'for https://api.pimlico.io/v2/137/rpc?apikey=' . PHA_PIMLICO_KEY . '&x=1 '
            . 'https://mainnet.helius-rpc.com/?api-key=abcDEF123 '
            . '{"api_key":"zzz999"} Authorization: Bearer eyJhbGciOi.payload.sig '
            . 'https://polygon-mainnet.g.alchemy.com/v2/alchemyKey123 '
            . 'raw helius-secret-value-123'
        );

        expect($redacted)->not->toContain(PHA_PIMLICO_KEY)
            ->not->toContain('abcDEF123')
            ->not->toContain('zzz999')
            ->not->toContain('eyJhbGciOi')
            ->not->toContain('alchemyKey123')
            ->not->toContain('helius-secret-value-123')
            ->toContain('apikey=[redacted]&x=1')
            ->toContain('Bearer [redacted]');
    });

    it('falls back to the per-chain Pimlico endpoint when PIMLICO_BUNDLER_URL is empty', function (?string $configured): void {
        config(['relayer.pimlico.bundler_url' => $configured]);
        Http::fake(['*' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x89'])]);

        $result = (new EthRpcClient())->bundlerCall(SupportedNetwork::BASE, 'eth_chainId');

        expect($result)->toBe('0x89');
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://api.pimlico.io/v2/8453/rpc?apikey=' . PHA_PIMLICO_KEY);
    })->with([
        'null'         => [null],
        'empty string' => [''],
    ]);

    it('keeps the key out of RpcException messages', function (): void {
        Http::fake(['*' => Http::failedConnection()]);

        try {
            (new EthRpcClient())->bundlerCall(SupportedNetwork::POLYGON, 'pm_sponsorUserOperation');
            $this->fail('Expected RpcException');
        } catch (RpcException $e) {
            expect($e->getMessage())->not->toContain(PHA_PIMLICO_KEY)
                ->toContain('apikey=[redacted]');
        }
    });

    it('keeps the key out of prepare error records, API payloads and logs', function (): void {
        Http::fake(['*' => Http::failedConnection()]);

        /** @var BundlerInterface&MockInterface $bundler */
        $bundler = Mockery::mock(BundlerInterface::class);
        $bundler->shouldReceive('getEntryPointAddress')->andReturn(EvmTokens::ENTRY_POINT_V06);
        /** @var SmartAccountService&MockInterface $smartAccounts */
        $smartAccounts = Mockery::mock(SmartAccountService::class);
        $smartAccounts->shouldReceive('getByAccountAddress')->andReturn(null);

        $preparer = new EvmUserOpPreparer($bundler, new PimlicoPaymasterService(new EthRpcClient()), $smartAccounts);
        $user = User::factory()->create();

        [$result, $logs] = phaCaptureLogs(fn (): array => $preparer->prepare(
            $user,
            '0x' . str_repeat('1', 40),
            '0x' . str_repeat('2', 40),
            'USDC',
            'polygon',
            '1.50',
            null,
            'quote-1',
        ));

        /** @var WalletSendRecord $record */
        $record = $result['record'];

        expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED)
            ->and($record->error_code)->toBe('PAYMASTER_REJECTED')
            ->and((string) $record->error_message)->not->toContain(PHA_PIMLICO_KEY)
            ->and((string) $record->fresh()?->error_message)->not->toContain(PHA_PIMLICO_KEY)
            ->and((string) json_encode($record->toApiResponse()))->not->toContain(PHA_PIMLICO_KEY)
            ->and((string) json_encode($record->toIntentStatusResponse()))->not->toContain(PHA_PIMLICO_KEY)
            ->and((string) json_encode($result['payload']))->not->toContain(PHA_PIMLICO_KEY);

        expect($logs)->not->toBeEmpty();
        foreach ($logs as $line) {
            expect($line)->not->toContain(PHA_PIMLICO_KEY);
        }
    });

    it('keeps the key out of submit error records and logs', function (): void {
        Http::fake(['*' => Http::failedConnection()]);

        $user = User::factory()->create();
        $record = WalletSendRecord::create([
            'public_id'         => 'pi_send_' . Str::random(20),
            'user_id'           => $user->id,
            'network'           => 'polygon',
            'asset'             => 'USDC',
            'amount'            => '1.00000000',
            'sender_address'    => '0x' . str_repeat('1', 40),
            'recipient_address' => '0x' . str_repeat('2', 40),
            'status'            => WalletSendRecord::STATUS_PENDING,
            'user_op_hash'      => '0x' . str_repeat('a', 64),
            'metadata'          => [
                'user_op' => [
                    'sender'               => '0x' . str_repeat('1', 40),
                    'nonce'                => '0x0',
                    'initCode'             => '0x',
                    'callData'             => '0xb61d27f6',
                    'callGasLimit'         => '0x1',
                    'verificationGasLimit' => '0x1',
                    'preVerificationGas'   => '0x1',
                    'maxFeePerGas'         => '0x1',
                    'maxPriorityFeePerGas' => '0x1',
                    'paymasterAndData'     => '0x',
                    'signature'            => '0x',
                ],
            ],
        ]);

        $submitter = new EvmUserOpSubmitter(new PimlicoBundlerService(new EthRpcClient()));

        [$record, $logs] = phaCaptureLogs(fn (): WalletSendRecord => $submitter->submit($record, '0x' . str_repeat('ab', 65)));

        expect($record->status)->toBe(WalletSendRecord::STATUS_FAILED)
            ->and($record->error_code)->toBe('BUNDLER_REJECTED')
            ->and((string) $record->error_message)->not->toContain(PHA_PIMLICO_KEY)
            ->and((string) json_encode($record->toApiResponse()))->not->toContain(PHA_PIMLICO_KEY);

        foreach ($logs as $line) {
            expect($line)->not->toContain(PHA_PIMLICO_KEY);
        }
    });

    it('redacts a key already stored in an older send record on the way out', function (): void {
        $user = User::factory()->create();
        $record = WalletSendRecord::create([
            'public_id'         => 'pi_send_' . Str::random(20),
            'user_id'           => $user->id,
            'network'           => 'polygon',
            'asset'             => 'USDC',
            'amount'            => '1.00000000',
            'sender_address'    => '0x' . str_repeat('1', 40),
            'recipient_address' => '0x' . str_repeat('2', 40),
            'status'            => WalletSendRecord::STATUS_FAILED,
            'error_code'        => 'PAYMASTER_REJECTED',
            'error_message'     => 'RPC connection failed: cURL error 6 for ?apikey=' . PHA_PIMLICO_KEY,
        ]);

        expect((string) json_encode($record->toApiResponse()))->not->toContain(PHA_PIMLICO_KEY)
            ->and((string) json_encode($record->toIntentStatusResponse()))->not->toContain(PHA_PIMLICO_KEY);
    });

    it('keeps the Helius key out of Solana RPC errors', function (): void {
        config([
            'services.helius.api_key' => 'helius-secret-key-0001',
            'wallet.solana.rpc_url'   => 'https://mainnet.helius-rpc.com',
        ]);
        Http::fake(['*' => Http::failedConnection()]);

        try {
            (new HeliusRpcClient())->getBalance('11111111111111111111111111111111');
            $this->fail('Expected SolanaRpcException');
        } catch (SolanaRpcException $e) {
            expect($e->getMessage())->not->toContain('helius-secret-key-0001')
                ->toContain('api-key=[redacted]');
        }
    });
});

/*
|--------------------------------------------------------------------------
| B2 — SMS
|--------------------------------------------------------------------------
*/

describe('SMS sending', function (): void {
    it('requires authentication for POST /api/v1/sms/send', function (): void {
        config(['sms.enabled' => true]);

        $this->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => 'hi'])
            ->assertUnauthorized();
    });

    it('answers 503 for an authenticated user while SMS is disabled', function (): void {
        config(['sms.enabled' => false, 'machinepay.enabled' => false]);
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);

        $this->postJson('/api/v1/sms/send', ['to' => '+37069912345', 'message' => 'hi'])
            ->assertStatus(503);
    });

    it('limits sends per user', function (): void {
        config([
            'sms.enabled'                        => true,
            'machinepay.enabled'                 => false,
            'sms.rate_limit.per_user_per_minute' => 2,
        ]);
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['read', 'write', 'delete']);

        // Invalid numbers stop at validation, so nothing is sent.
        $this->postJson('/api/v1/sms/send', ['to' => 'invalid', 'message' => 'hi'])->assertStatus(422);
        $this->postJson('/api/v1/sms/send', ['to' => 'invalid', 'message' => 'hi'])->assertStatus(422);
        $this->postJson('/api/v1/sms/send', ['to' => 'invalid', 'message' => 'hi'])->assertStatus(429);

        // Another user has their own budget.
        Sanctum::actingAs(User::factory()->create(), ['read', 'write', 'delete']);
        $this->postJson('/api/v1/sms/send', ['to' => 'invalid', 'message' => 'hi'])->assertStatus(422);
    });

    it('refuses to send from SmsService while SMS is disabled', function (): void {
        config(['sms.enabled' => false]);

        expect(fn () => app(SmsService::class)->send('+37069912345', 'Zelta', 'hello'))
            ->toThrow(RuntimeException::class, 'SMS service is not enabled');
    });

    it('closes the MCP sms.send tool while SMS is disabled', function (): void {
        config(['sms.enabled' => false]);

        /** @var SmsService&MockInterface $service */
        $service = Mockery::mock(SmsService::class);
        $service->shouldNotReceive('send');
        /** @var SmsPricingService&MockInterface $pricing */
        $pricing = Mockery::mock(SmsPricingService::class);
        $pricing->shouldNotReceive('getPriceForNumber');

        $result = (new SmsSendTool($service, $pricing, new SmsSendBudget()))->execute(['to' => '+37069912345', 'message' => 'hello']);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe('SMS service is not enabled');
    });
});

/*
|--------------------------------------------------------------------------
| B3 — double opt-in subscriptions
|--------------------------------------------------------------------------
*/

/**
 * The confirmation URL from the (single) queued confirmation mail.
 */
function phaConfirmationUrl(string $email): string
{
    $url = null;
    Mail::assertQueued(SubscriberConfirmation::class, function (SubscriberConfirmation $mail) use ($email, &$url): bool {
        if (! $mail->hasTo($email)) {
            return false;
        }
        $url = $mail->confirmationUrl;

        return true;
    });

    expect($url)->toBeString();

    return (string) $url;
}

describe('double opt-in subscriptions', function (): void {
    beforeEach(function (): void {
        Mail::fake();
    });

    it('stores a new subscriber as pending and sends only the confirmation email', function (): void {
        $this->postJson('/subscriber/landing', ['email' => 'new@example.com'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $subscriber = Subscriber::where('email', 'new@example.com')->firstOrFail();

        expect($subscriber->status)->toBe(Subscriber::STATUS_PENDING)
            ->and($subscriber->confirmed_at)->toBeNull()
            ->and($subscriber->confirmation_token_hash)->toHaveLength(64)
            ->and($subscriber->confirmation_sent_at)->not->toBeNull();

        Mail::assertQueued(SubscriberConfirmation::class, 1);
        Mail::assertNotQueued(SubscriberWelcome::class);

        // Pending subscribers never receive newsletters.
        expect(app(SubscriberEmailService::class)->sendNewsletter('News', 'Body'))->toBe(0);
    });

    it('stores only a hash of the confirmation token', function (): void {
        $this->postJson('/subscriber/landing', ['email' => 'hash@example.com'])->assertOk();

        parse_str((string) parse_url(phaConfirmationUrl('hash@example.com'), PHP_URL_QUERY), $query);
        $subscriber = Subscriber::where('email', 'hash@example.com')->firstOrFail();

        $token = $query['token'] ?? null;
        expect($token)->toBeString();
        assert(is_string($token));

        expect($subscriber->confirmation_token_hash)->toBe(hash('sha256', $token))
            ->and($subscriber->toArray())->not->toHaveKey('confirmation_token_hash');
    });

    it('activates the subscriber from the signed link and only then sends the welcome email', function (): void {
        $this->postJson('/subscriber/landing', ['email' => 'confirm@example.com'])->assertOk();
        $url = phaConfirmationUrl('confirm@example.com');

        // Opening the link only shows the confirm button.
        $this->get($url)->assertOk()->assertViewHas('title', 'Confirm your subscription');
        expect(Subscriber::where('email', 'confirm@example.com')->firstOrFail()->status)->toBe(Subscriber::STATUS_PENDING);

        $this->post($url)
            ->assertOk()
            ->assertViewIs('subscriber.confirmed')
            ->assertViewHas('title', 'Subscription confirmed');

        $subscriber = Subscriber::where('email', 'confirm@example.com')->firstOrFail();
        expect($subscriber->status)->toBe(Subscriber::STATUS_ACTIVE)
            ->and($subscriber->confirmed_at)->not->toBeNull()
            ->and($subscriber->confirmation_token_hash)->toBeNull();

        Mail::assertQueued(SubscriberWelcome::class, fn (SubscriberWelcome $mail): bool => $mail->hasTo('confirm@example.com'));

        // Re-opening the link or confirming again does not send another
        // welcome email.
        $this->get($url)->assertOk()->assertViewHas('title', 'Already confirmed');
        $this->post($url)->assertOk()->assertViewHas('title', 'Already confirmed');
        Mail::assertQueued(SubscriberWelcome::class, 1);
    });

    it('rejects a tampered, unsigned or expired confirmation link', function (): void {
        $this->postJson('/subscriber/landing', ['email' => 'tamper@example.com'])->assertOk();
        $subscriber = Subscriber::where('email', 'tamper@example.com')->firstOrFail();

        // Valid signature, wrong token.
        $wrongToken = URL::temporarySignedRoute('subscriber.confirm', now()->addHour(), [
            'subscriber' => $subscriber->id,
            'token'      => Str::random(64),
        ]);
        $this->get($wrongToken)->assertOk()->assertViewHas('title', 'Link expired or invalid');
        $this->post($wrongToken)->assertOk()->assertViewHas('title', 'Link expired or invalid');

        // Real token, signature removed.
        $real = phaConfirmationUrl('tamper@example.com');
        $unsigned = preg_replace('/&?(expires|signature)=[^&]*/', '', $real);
        $this->get((string) $unsigned)->assertOk()->assertViewHas('title', 'Link expired or invalid');
        $this->post((string) $unsigned)->assertOk()->assertViewHas('title', 'Link expired or invalid');

        // Real link after it expired.
        $this->travel(SubscriberEmailService::CONFIRMATION_TTL_HOURS + 1)->hours();
        $this->get($real)->assertOk()->assertViewHas('title', 'Link expired or invalid');
        $this->post($real)->assertOk()->assertViewHas('title', 'Link expired or invalid');

        expect($subscriber->fresh()?->status)->toBe(Subscriber::STATUS_PENDING);
        Mail::assertNotQueued(SubscriberWelcome::class);
    });

    it('re-sends the confirmation to a pending address at most once per window', function (): void {
        $service = app(SubscriberEmailService::class);

        $service->subscribe('again@example.com', Subscriber::SOURCE_LANDING);
        $service->subscribe('again@example.com', Subscriber::SOURCE_LANDING);
        Mail::assertQueued(SubscriberConfirmation::class, 1);

        $this->travel(SubscriberEmailService::THROTTLE_WINDOW_SECONDS + 60)->seconds();

        $service->subscribe('again@example.com', Subscriber::SOURCE_LANDING);
        Mail::assertQueued(SubscriberConfirmation::class, 2);
    });

    it('does not mail an already active subscriber again', function (): void {
        Subscriber::factory()->create([
            'email'        => 'active@example.com',
            'status'       => Subscriber::STATUS_ACTIVE,
            'confirmed_at' => now(),
            'tags'         => ['a'],
        ]);

        $this->postJson('/subscriber/landing', ['email' => 'active@example.com', 'tags' => ['b']])
            ->assertOk();

        Mail::assertNothingQueued();
        expect(Subscriber::where('email', 'active@example.com')->firstOrFail()->tags)->toContain('a', 'b');
    });

    it('throttles requests per email address', function (): void {
        foreach (range(1, SubscriberEmailService::MAX_REQUESTS_PER_EMAIL) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                ->postJson('/subscriber/landing', ['email' => 'flood@example.com'])
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->postJson('/subscriber/landing', ['email' => 'flood@example.com'])
            ->assertStatus(429);

        Mail::assertQueued(SubscriberConfirmation::class, 1);
    });

    it('throttles requests per IP address', function (): void {
        $service = app(SubscriberEmailService::class);

        foreach (range(1, SubscriberEmailService::MAX_REQUESTS_PER_IP) as $i) {
            $service->subscribe("ip{$i}@example.com", Subscriber::SOURCE_LANDING, [], '203.0.113.7');
        }

        expect(fn () => $service->subscribe('ip-over@example.com', Subscriber::SOURCE_LANDING, [], '203.0.113.7'))
            ->toThrow(SubscriptionThrottledException::class);

        expect(Subscriber::where('email', 'ip-over@example.com')->exists())->toBeFalse();
    });

    it('makes the CGO notify form double opt-in', function (): void {
        $this->post('/cgo/notify', ['email' => 'cgo@example.com'])
            ->assertRedirect(route('cgo.notify-success'));

        $subscriber = Subscriber::where('email', 'cgo@example.com')->firstOrFail();
        expect($subscriber->status)->toBe(Subscriber::STATUS_PENDING)
            ->and($subscriber->source)->toBe(Subscriber::SOURCE_CGO);
        expect(DB::table('cgo_notifications')->where('email', 'cgo@example.com')->exists())->toBeTrue();

        Mail::assertQueued(SubscriberConfirmation::class, 1);
        Mail::assertNotSent(CgoNotificationReceived::class);
        Mail::assertNotQueued(CgoNotificationReceived::class);

        // The confirm button posts to the signed link.
        $this->post(phaConfirmationUrl('cgo@example.com'))->assertOk();

        Mail::assertSent(CgoNotificationReceived::class, fn (CgoNotificationReceived $mail): bool => $mail->hasTo('cgo@example.com'));
    });

    it('throttles the CGO notify form per email address', function (): void {
        foreach (range(1, SubscriberEmailService::MAX_REQUESTS_PER_EMAIL) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.{$i}"])
                ->post('/cgo/notify', ['email' => 'cgoflood@example.com'])
                ->assertRedirect(route('cgo.notify-success'));
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.99'])
            ->from('/cgo')
            ->post('/cgo/notify', ['email' => 'cgoflood@example.com'])
            ->assertRedirect('/cgo')
            ->assertSessionHasErrors('email');
    });
});

/*
|--------------------------------------------------------------------------
| B4 — card JIT funding and issuer webhooks
|--------------------------------------------------------------------------
*/

/**
 * @return array{0: JitFundingService, 1: User}
 */
function phaJitService(bool $withAccount = true, ?int $usdcBalanceMinor = 50_000_000): array
{
    DB::table('assets')->updateOrInsert(
        ['code' => 'USDC'],
        ['name' => 'USD Coin', 'type' => 'crypto', 'precision' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
    );

    $user = User::factory()->create();

    if ($withAccount) {
        $account = Account::factory()->create(['user_uuid' => $user->uuid]);
        if ($usdcBalanceMinor !== null) {
            AccountBalance::query()->updateOrCreate(
                ['account_uuid' => $account->uuid, 'asset_code' => 'USDC'],
                ['balance' => $usdcBalanceMinor],
            );
        }
    }

    /** @var CardIssuerInterface&MockInterface $issuer */
    $issuer = Mockery::mock(CardIssuerInterface::class);
    $issuer->shouldReceive('getCard')->andReturn(new VirtualCard(
        cardToken: 'card_pha_001',
        last4: '4242',
        network: CardNetwork::VISA,
        status: CardStatus::ACTIVE,
        cardholderName: 'Test Holder',
        expiresAt: new DateTimeImmutable('+1 year'),
        metadata: ['user_id' => $user->uuid],
    ));

    /** @var MetricsService&MockInterface $metrics */
    $metrics = Mockery::mock(MetricsService::class);
    $metrics->shouldIgnoreMissing();

    return [new JitFundingService($issuer, app(SpendLimitEnforcementService::class), $metrics), $user];
}

function phaAuthRequest(int $amountCents): AuthorizationRequest
{
    return new AuthorizationRequest(
        authorizationId: 'auth_' . Str::random(10),
        cardToken: 'card_pha_001',
        amountCents: $amountCents,
        currency: 'USD',
        merchantName: 'Coffee',
        merchantCategory: '5814',
    );
}

describe('card JIT funding', function (): void {
    beforeEach(function (): void {
        Event::fake([AuthorizationApproved::class, AuthorizationDeclined::class]);
        config(['cardissuance.default_issuer' => 'marqeta', 'cardissuance.jit_funding.max_auth_per_minute' => 100]);
    });

    it('approves against the real balance and holds approved amounts', function (): void {
        [$service] = phaJitService(usdcBalanceMinor: 50_000_000); // 50 USDC

        $first = $service->authorize(phaAuthRequest(2_000));  // 20.00
        $second = $service->authorize(phaAuthRequest(2_500)); // 25.00 (45 held)
        $third = $service->authorize(phaAuthRequest(1_000));  // 10.00 would exceed 50

        expect($first['approved'])->toBeTrue()
            ->and($first['hold_id'])->toStartWith('hold_')
            ->and($second['approved'])->toBeTrue()
            ->and($third['approved'])->toBeFalse()
            ->and($third['decision'])->toBe(AuthorizationDecision::DECLINED_INSUFFICIENT_FUNDS);
    });

    it('declines when the balance is insufficient', function (): void {
        [$service] = phaJitService(usdcBalanceMinor: 5_000_000); // 5 USDC

        $result = $service->authorize(phaAuthRequest(1_000)); // 10.00

        expect($result['approved'])->toBeFalse()
            ->and($result['decision'])->toBe(AuthorizationDecision::DECLINED_INSUFFICIENT_FUNDS);
    });

    it('declines when the cardholder has no funding account', function (): void {
        [$service] = phaJitService(withAccount: false);

        expect($service->authorize(phaAuthRequest(100))['approved'])->toBeFalse();
    });

    it('declines when the funding asset is not configured', function (): void {
        [$service] = phaJitService();
        DB::table('account_balances')->where('asset_code', 'USDC')->delete();
        DB::table('assets')->where('code', 'USDC')->delete();

        expect($service->authorize(phaAuthRequest(100))['approved'])->toBeFalse();
    });

    it('declines when the hold store is unavailable', function (): void {
        [$service] = phaJitService(usdcBalanceMinor: 50_000_000);
        app(Illuminate\Cache\RateLimiter::class); // keep the real limiter store

        Cache::partialMock()->shouldReceive('increment')->andThrow(new RuntimeException('cache down'));

        expect($service->authorize(phaAuthRequest(100))['approved'])->toBeFalse();
    });

    it('uses the demo balance only when the configured issuer is demo', function (): void {
        [$service] = phaJitService(withAccount: false);

        config(['cardissuance.default_issuer' => 'demo']);
        expect($service->authorize(phaAuthRequest(100))['approved'])->toBeTrue();

        config(['cardissuance.default_issuer' => 'marqeta']);
        expect($service->authorize(phaAuthRequest(100))['approved'])->toBeFalse();
    });
});

describe('card issuer webhook authentication', function (): void {
    beforeEach(function (): void {
        config([
            'cardissuance.issuers.marqeta.webhook_username' => null,
            'cardissuance.issuers.marqeta.webhook_password' => null,
            'cardissuance.issuers.marqeta.webhook_secret'   => null,
            'cardissuance.issuers.marqeta.hmac_secret'      => null,
        ]);
    });

    $settlement = [
        'authorization_id' => 'auth_1',
        'settlement_id'    => 'set_1',
        'final_amount'     => 1000,
        'currency'         => 'USD',
    ];

    it('answers 401 in production when no credentials are configured', function () use ($settlement): void {
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->postJson('/api/webhooks/card-issuer/settlement', $settlement)->assertUnauthorized();
        $this->postJson('/api/webhooks/card-issuer/authorization', [
            'authorization_id' => 'a', 'card_token' => 'c', 'amount' => 100, 'currency' => 'USD', 'merchant_name' => 'm',
        ])->assertUnauthorized();
    });

    it('accepts unconfigured webhooks only in local/testing', function () use ($settlement): void {
        $this->postJson('/api/webhooks/card-issuer/settlement', $settlement)->assertOk();

        $this->app->detectEnvironment(fn (): string => 'staging');
        $this->postJson('/api/webhooks/card-issuer/settlement', $settlement)->assertUnauthorized();
    });

    it('requires matching Basic Auth credentials when configured', function () use ($settlement): void {
        $this->app->detectEnvironment(fn (): string => 'production');
        config([
            'cardissuance.issuers.marqeta.webhook_username' => 'marqeta',
            'cardissuance.issuers.marqeta.webhook_password' => 's3cret-pass',
        ]);

        $this->postJson('/api/webhooks/card-issuer/settlement', $settlement)->assertUnauthorized();
        $this->withHeader('Authorization', 'Basic ' . base64_encode('marqeta:wrong'))
            ->postJson('/api/webhooks/card-issuer/settlement', $settlement)->assertUnauthorized();
        $this->withHeader('Authorization', 'Basic ' . base64_encode('marqeta:s3cret-pass'))
            ->postJson('/api/webhooks/card-issuer/settlement', $settlement)->assertOk();
    });

    it('rejects a half-configured Basic Auth pair', function () use ($settlement): void {
        config(['cardissuance.issuers.marqeta.webhook_username' => 'marqeta']);

        $this->withHeader('Authorization', 'Basic ' . base64_encode('marqeta:'))
            ->postJson('/api/webhooks/card-issuer/settlement', $settlement)->assertUnauthorized();
    });

    it('requires the HMAC signature header once a secret is configured', function () use ($settlement): void {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['cardissuance.issuers.marqeta.webhook_secret' => 'hmac-secret-value']);

        $body = (string) json_encode($settlement);
        $signature = hash_hmac('sha256', $body, 'hmac-secret-value');

        $this->call('POST', '/api/webhooks/card-issuer/settlement', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], $body)
            ->assertUnauthorized();
        $this->call('POST', '/api/webhooks/card-issuer/settlement', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_MARQETA_WEBHOOK_SIGNATURE' => str_repeat('0', 64)], $body)
            ->assertUnauthorized();
        $this->call('POST', '/api/webhooks/card-issuer/settlement', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_MARQETA_WEBHOOK_SIGNATURE' => $signature], $body)
            ->assertOk();
    });
});

/*
|--------------------------------------------------------------------------
| B5 — generic relayer sponsorship
|--------------------------------------------------------------------------
*/

const PHA_SENDER = '0x1111111111111111111111111111111111111111';
const PHA_RECIPIENT = '0x2222222222222222222222222222222222222222';

function phaTransferCallData(string $token = 'USDC', string $network = 'polygon', string $amount = '1500000', string $to = PHA_RECIPIENT): string
{
    return UserOpCallDataBuilder::buildExecute(
        (string) EvmTokens::contractFor($token, $network),
        Erc20Transfer::encodeTransferCallData($to, $amount),
    );
}

function phaOwnSmartAccount(User $user, string $network = 'polygon', bool $deployed = true): SmartAccount
{
    /** @var SmartAccount $account */
    $account = SmartAccount::query()->create([
        'user_id'         => $user->id,
        'owner_address'   => '0x' . str_repeat('3', 40),
        'account_address' => strtolower(PHA_SENDER),
        'network'         => $network,
        'deployed'        => $deployed,
        'nonce'           => 0,
        'pending_ops'     => 0,
    ]);

    return $account;
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function phaSponsorBody(array $overrides = []): array
{
    return array_merge([
        'user_address' => PHA_SENDER,
        'call_data'    => phaTransferCallData(),
        'signature'    => '0x' . str_repeat('ab', 65),
        'network'      => 'polygon',
        'fee_token'    => 'USDC',
    ], $overrides);
}

/**
 * @return GasStationService&MockInterface
 */
function phaMockGasStation(): MockInterface
{
    /** @var GasStationService&MockInterface $gas */
    $gas = Mockery::mock(GasStationService::class);
    app()->instance(GasStationService::class, $gas);

    return $gas;
}

function phaSponsorMutation(): string
{
    return 'mutation ($input: SponsorTransactionInput!) { sponsorTransaction(input: $input) }';
}

describe('generic relayer sponsorship', function (): void {
    beforeEach(function (): void {
        config(['wallet.evm.enabled_networks' => ['polygon', 'base', 'arbitrum']]);
    });

    it('answers 404 on REST and an error on GraphQL while disabled', function (): void {
        config(['relayer.generic_sponsor_enabled' => false]);
        $user = User::factory()->create();
        phaOwnSmartAccount($user);

        $gas = phaMockGasStation();
        $gas->shouldNotReceive('sponsorTransaction');

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/relayer/sponsor', phaSponsorBody())->assertNotFound();

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query'     => phaSponsorMutation(),
            'variables' => ['input' => ['network' => 'polygon', 'sender' => PHA_SENDER, 'data' => phaTransferCallData()]],
        ]);
        expect($response->json('errors.0.message'))->toBe('Transaction sponsorship is not available.')
            ->and($response->json('data.sponsorTransaction'))->toBeNull();
    });

    it('sponsors a USDC transfer from the caller\'s own smart account', function (): void {
        config(['relayer.generic_sponsor_enabled' => true]);
        $user = User::factory()->create();
        phaOwnSmartAccount($user);

        $gas = phaMockGasStation();
        $gas->shouldReceive('sponsorTransaction')
            ->twice()
            ->withArgs(fn (string $userAddress, string $callData, string $signature, SupportedNetwork $network): bool => $userAddress === PHA_SENDER && $network === SupportedNetwork::POLYGON)
            ->andReturn(['tx_hash' => null, 'user_op_hash' => '0x' . str_repeat('c', 64)]);

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/relayer/sponsor', phaSponsorBody())
            ->assertOk()
            ->assertJsonPath('success', true);

        $response = $this->actingAs($user, 'sanctum')->postJson('/graphql', [
            'query'     => phaSponsorMutation(),
            'variables' => ['input' => ['network' => 'polygon', 'sender' => PHA_SENDER, 'data' => phaTransferCallData()]],
        ]);
        expect($response->json('errors'))->toBeNull()
            ->and((string) $response->json('data.sponsorTransaction'))->toContain('user_op_hash');
    });

    it('accepts a sender registered as an active wallet address', function (): void {
        config(['relayer.generic_sponsor_enabled' => true]);
        $user = User::factory()->create();
        BlockchainAddress::withoutEvents(fn () => BlockchainAddress::query()->create([
            'user_uuid'  => $user->uuid,
            'chain'      => 'base',
            'address'    => strtolower(PHA_SENDER),
            'public_key' => '',
            'is_active'  => true,
        ]));

        $network = app(SponsorshipRequestValidator::class)->validate($user, PHA_SENDER, phaTransferCallData('USDC', 'base'), 'base');

        expect($network)->toBe(SupportedNetwork::BASE);
    });

    it('rejects a sender that belongs to another user', function (): void {
        config(['relayer.generic_sponsor_enabled' => true]);
        phaOwnSmartAccount(User::factory()->create());
        $attacker = User::factory()->create();

        $gas = phaMockGasStation();
        $gas->shouldNotReceive('sponsorTransaction');

        Sanctum::actingAs($attacker, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/relayer/sponsor', phaSponsorBody())
            ->assertForbidden()
            ->assertJsonPath('error.code', SponsorshipRequestRejectedException::CODE_SENDER_NOT_OWNED);

        $response = $this->actingAs($attacker, 'sanctum')->postJson('/graphql', [
            'query'     => phaSponsorMutation(),
            'variables' => ['input' => ['network' => 'polygon', 'sender' => PHA_SENDER, 'data' => phaTransferCallData()]],
        ]);
        expect((string) $response->json('errors.0.message'))->toContain('registered to your account')
            ->and($response->json('data.sponsorTransaction'))->toBeNull();
    });

    it('rejects calldata other than a stablecoin transfer', function (string $callData): void {
        config(['relayer.generic_sponsor_enabled' => true]);
        $user = User::factory()->create();
        phaOwnSmartAccount($user);

        $gas = phaMockGasStation();
        $gas->shouldNotReceive('sponsorTransaction');

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/relayer/sponsor', phaSponsorBody(['call_data' => $callData]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', SponsorshipRequestRejectedException::CODE_CALLDATA_NOT_ALLOWED);
    })->with([
        'empty'                  => ['0x'],
        'arbitrary'              => ['0xabcdef'],
        'non-stablecoin target'  => [UserOpCallDataBuilder::buildExecute('0x' . str_repeat('9', 40), Erc20Transfer::encodeTransferCallData(PHA_RECIPIENT, '1'))],
        'other network contract' => [phaTransferCallData('USDC', 'arbitrum')],
        'approve instead'        => [UserOpCallDataBuilder::buildExecute((string) EvmTokens::contractFor('USDC', 'polygon'), '0x095ea7b3' . str_repeat('0', 24) . substr(PHA_RECIPIENT, 2) . str_repeat('f', 64))],
        'non-zero value'         => [substr_replace(phaTransferCallData(), str_pad('1', 64, '0', STR_PAD_LEFT), 2 + 8 + 64, 64)],
        'zero amount'            => [phaTransferCallData('USDC', 'polygon', '0')],
        'trailing bytes'         => [phaTransferCallData() . 'ff'],
    ]);

    it('only sponsors on WALLET_SEND_EVM_NETWORKS', function (): void {
        config(['relayer.generic_sponsor_enabled' => true]);
        $user = User::factory()->create();
        phaOwnSmartAccount($user, 'ethereum');

        Sanctum::actingAs($user, ['read', 'write', 'delete']);
        $this->postJson('/api/v1/relayer/sponsor', phaSponsorBody([
            'network'   => 'ethereum',
            'call_data' => phaTransferCallData('USDC', 'ethereum'),
        ]))
            ->assertStatus(422)
            ->assertJsonPath('error.code', SponsorshipRequestRejectedException::CODE_NETWORK_NOT_ALLOWED);
    });

    it('accepts USDT and rejects USDT where it has no contract', function (): void {
        config(['relayer.generic_sponsor_enabled' => true]);
        $user = User::factory()->create();
        phaOwnSmartAccount($user, 'arbitrum');
        $validator = app(SponsorshipRequestValidator::class);

        expect($validator->validate($user, PHA_SENDER, phaTransferCallData('USDT', 'arbitrum'), 'arbitrum'))
            ->toBe(SupportedNetwork::ARBITRUM);

        // No USDT on Base: a USDT-shaped call aimed at the Polygon contract is refused there.
        phaOwnSmartAccount($user, 'base');
        expect(fn () => $validator->validate($user, PHA_SENDER, phaTransferCallData('USDT', 'polygon'), 'base'))
            ->toThrow(SponsorshipRequestRejectedException::class);
    });

    it('only accepts an init code that deploys the caller\'s own smart account', function (): void {
        config(['relayer.generic_sponsor_enabled' => true]);
        $user = User::factory()->create();
        $account = phaOwnSmartAccount($user, 'polygon', deployed: false);
        $expectedInitCode = app(SmartAccountService::class)->getInitCode((string) $account->owner_address, 'polygon');
        $validator = app(SponsorshipRequestValidator::class);

        expect($validator->validate($user, PHA_SENDER, phaTransferCallData(), 'polygon', $expectedInitCode))
            ->toBe(SupportedNetwork::POLYGON);

        expect(fn () => $validator->validate($user, PHA_SENDER, phaTransferCallData(), 'polygon', '0x' . str_repeat('ab', 40)))
            ->toThrow(SponsorshipRequestRejectedException::class);
    });
});

/*
|--------------------------------------------------------------------------
| B6 — API registration switch
|--------------------------------------------------------------------------
*/

/**
 * @return array<string, mixed>
 */
function phaRegisterBody(string $email): array
{
    return [
        'name'                  => 'New Person',
        'email'                 => $email,
        'password'              => 'Sup3r-Secret-Pass!',
        'password_confirmation' => 'Sup3r-Secret-Pass!',
    ];
}

function phaInvite(string $email, ?DateTimeInterface $expiresAt = null): UserInvitation
{
    /** @var UserInvitation $invitation */
    $invitation = UserInvitation::query()->create([
        'email'      => $email,
        'token'      => Str::random(64),
        'role'       => 'private',
        'invited_by' => User::factory()->create()->id,
        'expires_at' => $expiresAt ?? now()->addDay(),
    ]);

    return $invitation;
}

describe('API registration switch: password sign-up', function (): void {
    it('answers 403 ERR_REG_001 on v1 and v2 while closed', function (string $path): void {
        config(['registration.api_enabled' => false]);

        $this->postJson($path, phaRegisterBody('closed@example.com'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_001')
            ->assertJsonPath('error.reason', 'REGISTRATION_CLOSED');

        expect(User::where('email', 'closed@example.com')->exists())->toBeFalse();
    })->with(['/api/auth/register', '/api/v2/auth/register']);

    it('does not reveal existing emails while closed', function (): void {
        config(['registration.api_enabled' => false]);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', phaRegisterBody('taken@example.com'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_001');
    });

    it('registers while open', function (string $path): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false]);

        $this->postJson($path, phaRegisterBody('open@example.com'))->assertCreated();

        expect(User::where('email', 'open@example.com')->exists())->toBeTrue();
    })->with(['/api/auth/register', '/api/v2/auth/register']);

    it('requires a valid invitation in invite-only mode', function (): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => true]);

        $this->postJson('/api/auth/register', phaRegisterBody('uninvited@example.com'))
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_002')
            ->assertJsonPath('error.reason', 'INVITATION_REQUIRED');

        $expired = phaInvite('expired@example.com', now()->subMinute());
        $this->postJson('/api/auth/register', phaRegisterBody('expired@example.com') + ['invitation_token' => $expired->token])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_002');

        $invitation = phaInvite('Invited@Example.com');
        $this->postJson('/api/v2/auth/register', phaRegisterBody('invited@example.com') + ['invitation_token' => $invitation->token])
            ->assertCreated();

        expect($invitation->fresh()?->accepted_at)->not->toBeNull();

        // A used invitation does not admit a second account.
        $this->postJson('/api/auth/register', phaRegisterBody('invited2@example.com') + ['invitation_token' => $invitation->token])
            ->assertForbidden();
    });

    it('needs the invitation token as well as the invited email in invite-only mode', function (string $path): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => true]);
        $invitation = phaInvite('invitee@example.com');
        $otherInvitation = phaInvite('someone-else@example.com');

        $attempts = [
            'email only'                  => [],
            'empty token'                 => ['invitation_token' => ''],
            'wrong token'                 => ['invitation_token' => Str::random(64)],
            'token of another invitation' => ['invitation_token' => $otherInvitation->token],
            'token in another case'       => ['invitation_token' => strtoupper($invitation->token)],
        ];
        foreach ($attempts as $extra) {
            $this->postJson($path, phaRegisterBody('invitee@example.com') + $extra)
                ->assertForbidden()
                ->assertJsonPath('error.code', 'ERR_REG_002')
                ->assertJsonPath('error.reason', 'INVITATION_REQUIRED');
        }

        expect(User::where('email', 'invitee@example.com')->exists())->toBeFalse();
        expect($invitation->fresh()?->accepted_at)->toBeNull();
        expect($otherInvitation->fresh()?->accepted_at)->toBeNull();

        $this->postJson($path, phaRegisterBody('invitee@example.com') + ['invitation_token' => $invitation->token])
            ->assertCreated();

        expect($invitation->fresh()?->accepted_at)->not->toBeNull();
        expect($otherInvitation->fresh()?->accepted_at)->toBeNull();
    })->with(['/api/auth/register', '/api/v2/auth/register']);

    it('ignores the invitation token while registration is open', function (): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false]);

        $this->postJson('/api/auth/register', phaRegisterBody('open-token@example.com') + ['invitation_token' => 'anything'])
            ->assertCreated();
    });
});

/*
| Privy login (mobile).
*/

/**
 * @return array{privatePem: string, kid: string}
 */
function phaPrivySetup(): array
{
    config([
        'privy.app_id'                 => 'pha-app-id',
        'privy.app_secret'             => 'pha-secret',
        'privy.jwks_url'               => 'https://privy.example/.well-known/jwks.json',
        'privy.issuer'                 => 'privy.io',
        'privy.jwks_cache_ttl_seconds' => 3600,
    ]);

    $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($resource === false) {
        throw new RuntimeException('Failed to generate RSA keypair');
    }
    $privatePem = '';
    openssl_pkey_export($resource, $privatePem);
    $details = openssl_pkey_get_details($resource);
    if ($details === false) {
        throw new RuntimeException('Failed to read RSA key details');
    }

    $b64 = static fn (string $bin): string => rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    $kid = 'pha-kid';
    $jwks = ['keys' => [['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => $kid, 'n' => $b64($details['rsa']['n']), 'e' => $b64($details['rsa']['e'])]]];

    /** @var ClientInterface&MockInterface $http */
    $http = Mockery::mock(ClientInterface::class);
    /** @var Mockery\Expectation $expectation */
    $expectation = $http->shouldReceive('request');
    $expectation->andReturnUsing(
        fn (string $method, string $url) => $url === 'https://privy.example/.well-known/jwks.json'
            ? new PsrResponse(200, [], (string) json_encode($jwks))
            : new PsrResponse(404, [], '{}')
    );
    app()->bind(PrivyJwtVerifier::class, fn () => new PrivyJwtVerifier($http));

    return ['privatePem' => $privatePem, 'kid' => $kid];
}

/**
 * @param  array{privatePem: string, kid: string}  $keys
 */
function phaPrivyToken(array $keys, string $did, string $email): string
{
    return JWT::encode([
        'sub'             => $did,
        'iss'             => 'privy.io',
        'aud'             => 'pha-app-id',
        'iat'             => time() - 60,
        'exp'             => time() + 3600,
        'linked_accounts' => [['type' => 'email', 'address' => $email]],
    ], $keys['privatePem'], 'RS256', $keys['kid']);
}

describe('API registration switch: Privy login', function (): void {
    it('blocks new Privy identities while closed but keeps existing users logging in', function (): void {
        $keys = phaPrivySetup();
        config(['registration.api_enabled' => false]);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => phaPrivyToken($keys, 'did:privy:newcomer000001', 'newcomer@example.com')])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_001');
        expect(User::where('email', 'newcomer@example.com')->exists())->toBeFalse();

        User::factory()->create([
            'email'           => 'returning@example.com',
            'privy_user_id'   => 'did:privy:returning00001',
            'privy_linked_at' => now(),
        ]);
        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => phaPrivyToken($keys, 'did:privy:returning00001', 'returning@example.com')])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', false);
    });

    it('creates the account while open', function (): void {
        $keys = phaPrivySetup();
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false]);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => phaPrivyToken($keys, 'did:privy:opennew000001', 'opennew@example.com')])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true);
    });

    it('admits invite-only sign-ups by the Privy-verified email', function (): void {
        $keys = phaPrivySetup();
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => true]);

        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => phaPrivyToken($keys, 'did:privy:noinvite00001', 'noinvite@example.com')])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_002');

        $invitation = phaInvite('withinvite@example.com');
        $this->postJson('/api/v1/auth/privy-login', ['privy_token' => phaPrivyToken($keys, 'did:privy:withinvite001', 'withinvite@example.com')])
            ->assertOk()
            ->assertJsonPath('data.is_new_user', true);

        expect($invitation->fresh()?->accepted_at)->not->toBeNull();
    });
});

/*
| Social login and web Privy login.
*/

function phaFakeSocialUser(string $email, string $id): void
{
    $socialUser = (new SocialiteUser())->map(['id' => $id, 'name' => 'Social Person', 'email' => $email, 'avatar' => null]);

    $provider = Mockery::mock();
    /** @var Mockery\Expectation $stateless */
    $stateless = $provider->shouldReceive('stateless');
    $stateless->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

describe('API registration switch: social and web Privy sign-up', function (): void {
    it('blocks social auto-create while closed', function (): void {
        config(['registration.api_enabled' => false]);

        phaFakeSocialUser('socialnew@example.com', 'g-new');
        $this->postJson('/api/auth/social/google/callback', ['code' => 'abc'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ERR_REG_001');
        expect(User::where('email', 'socialnew@example.com')->exists())->toBeFalse();
    });

    it('keeps existing social users logging in while closed', function (): void {
        config(['registration.api_enabled' => false]);

        // An existing social user signs in through their linked identity;
        // a bare email match no longer signs in (GHSA-rrfq).
        User::factory()->create(['email' => 'socialold@example.com', 'oauth_provider' => 'google', 'oauth_id' => 'g-old']);
        phaFakeSocialUser('socialold@example.com', 'g-old');
        $this->postJson('/api/auth/social/google/callback', ['code' => 'abc'])->assertOk();
    });

    it('creates social users while open', function (): void {
        config(['registration.api_enabled' => true, 'registration.api_invite_only' => false]);

        phaFakeSocialUser('socialopen@example.com', 'g-open');
        $this->postJson('/api/auth/social/google/callback', ['code' => 'abc'])->assertOk();

        expect(User::where('email', 'socialopen@example.com')->exists())->toBeTrue();
    });

    it('blocks web Privy sign-up while closed', function (): void {
        config(['registration.api_enabled' => false, 'privy.web_login_enabled' => true]);

        /** @var PrivyEmailOtpClient&MockInterface $otp */
        $otp = Mockery::mock(PrivyEmailOtpClient::class);
        app()->instance(PrivyEmailOtpClient::class, $otp);
        // A new address is refused before Privy is called.
        $otp->shouldNotReceive('loginWithCode');

        $this->post('/login/privy/verify', ['email' => 'webnew@example.com', 'code' => '123456'])
            ->assertRedirect(route('login', ['email' => 'webnew@example.com', 'step' => 'verify']))
            ->assertSessionHasErrors('code');

        expect(User::where('email', 'webnew@example.com')->exists())->toBeFalse();
        $this->assertGuest();
    });
});
